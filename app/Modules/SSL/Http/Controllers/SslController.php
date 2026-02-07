<?php

declare(strict_types=1);

namespace App\Modules\SSL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domain\Models\Domain;
use App\Modules\SSL\Http\Requests\UploadCustomCertRequest;
use App\Modules\SSL\Http\Resources\SslCertificateResource;
use App\Modules\SSL\Http\Resources\SslCertificateCollection;
use App\Modules\SSL\Models\SslCertificate;
use App\Modules\SSL\Services\SslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SslController extends Controller
{
    public function __construct(
        protected SslService $sslService
    ) {}

    /**
     * List all SSL certificates.
     */
    public function index(Request $request): SslCertificateCollection
    {
        $query = SslCertificate::with('domain');

        // Filter by user for non-admins
        if (!$request->user()->isAdmin()) {
            $query->whereHas('domain', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('expiring')) {
            $query->expiringSoon((int) $request->expiring);
        }

        $certificates = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return new SslCertificateCollection($certificates);
    }

    /**
     * Issue a Let's Encrypt certificate for a domain.
     */
    public function issueLetsEncrypt(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        try {
            $certificate = $this->sslService->issueLetsEncrypt($domain);

            return response()->json([
                'success' => true,
                'message' => "Let's Encrypt certificate issued successfully.",
                'data' => new SslCertificateResource($certificate),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSL_ISSUE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Upload a custom SSL certificate for a domain.
     */
    public function uploadCustom(UploadCustomCertRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validated();

        try {
            $certificate = $this->sslService->uploadCustomCert(
                $domain,
                $validated['certificate'],
                $validated['private_key'],
                $validated['ca_bundle'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Custom certificate uploaded successfully.',
                'data' => new SslCertificateResource($certificate),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSL_UPLOAD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Show a specific SSL certificate.
     */
    public function show(SslCertificate $ssl): JsonResponse
    {
        $this->authorize('view', $ssl);

        $ssl->load('domain');

        return response()->json([
            'success' => true,
            'data' => new SslCertificateResource($ssl),
        ]);
    }

    /**
     * Get detailed certificate information.
     */
    public function info(SslCertificate $ssl): JsonResponse
    {
        $this->authorize('view', $ssl);

        try {
            $info = [];
            if ($ssl->certificate_path) {
                $info = $this->sslService->getCertificateInfo($ssl->certificate_path);
            }

            return response()->json([
                'success' => true,
                'data' => array_merge(
                    (new SslCertificateResource($ssl))->toArray(request()),
                    ['certificate_details' => $info]
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CERT_INFO_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Manually renew a certificate.
     */
    public function renew(SslCertificate $ssl): JsonResponse
    {
        $this->authorize('update', $ssl);

        if (!$ssl->isLetsEncrypt()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_RENEW',
                    'message' => "Only Let's Encrypt certificates can be renewed.",
                ],
            ], 422);
        }

        try {
            $certificate = $this->sslService->renewCertificate($ssl);

            return response()->json([
                'success' => true,
                'message' => 'Certificate renewed successfully.',
                'data' => new SslCertificateResource($certificate),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RENEW_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Toggle auto-renew setting.
     */
    public function toggleAutoRenew(SslCertificate $ssl): JsonResponse
    {
        $this->authorize('update', $ssl);

        if (!$ssl->isLetsEncrypt()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_APPLICABLE',
                    'message' => "Auto-renew is only available for Let's Encrypt certificates.",
                ],
            ], 422);
        }

        $ssl->update(['auto_renew' => !$ssl->auto_renew]);

        return response()->json([
            'success' => true,
            'message' => $ssl->auto_renew
                ? 'Auto-renewal enabled.'
                : 'Auto-renewal disabled.',
            'data' => new SslCertificateResource($ssl->fresh()),
        ]);
    }

    /**
     * Revoke and remove a certificate.
     */
    public function destroy(SslCertificate $ssl): JsonResponse
    {
        $this->authorize('delete', $ssl);

        try {
            $this->sslService->revokeCertificate($ssl);

            return response()->json([
                'success' => true,
                'message' => 'Certificate revoked and deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REVOKE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Check certificates expiring within given days.
     */
    public function checkExpiry(Request $request): SslCertificateCollection
    {
        $this->authorize('viewAny', SslCertificate::class);

        $days = (int) $request->get('days', 14);

        $query = SslCertificate::with('domain')
            ->expiringSoon($days);

        // Filter by user for non-admins
        if (!$request->user()->isAdmin()) {
            $query->whereHas('domain', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        $certificates = $query->orderBy('expires_at', 'asc')->get();

        return new SslCertificateCollection($certificates);
    }
}
