<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Requests\ConfirmTwoFactorRequest;
use App\Modules\Auth\Http\Requests\DisableTwoFactorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Enable 2FA - Generate secret and QR code.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if already enabled
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => '2FA_ALREADY_ENABLED',
                    'message' => 'Two-factor authentication is already enabled.',
                ],
            ], 422);
        }

        // Generate secret
        $secret = $this->google2fa->generateSecretKey();

        // Store encrypted secret (not confirmed yet)
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ]);

        // Generate QR code
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'qr_code_svg' => base64_encode($qrCodeSvg),
                'qr_code_url' => $qrCodeUrl,
            ],
            'message' => 'Scan the QR code with your authenticator app, then confirm with a code.',
        ]);
    }

    /**
     * Confirm 2FA with a code.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => '2FA_NOT_INITIATED',
                    'message' => 'Please enable 2FA first.',
                ],
            ], 422);
        }

        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => '2FA_ALREADY_CONFIRMED',
                    'message' => 'Two-factor authentication is already confirmed.',
                ],
            ], 422);
        }

        // Verify the code
        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_2FA_CODE',
                    'message' => 'Invalid verification code.',
                ],
            ], 422);
        }

        // Confirm 2FA
        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'two_factor_enabled' => true,
            ],
            'message' => 'Two-factor authentication has been enabled.',
        ]);
    }

    /**
     * Disable 2FA.
     */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'Password is incorrect.',
                ],
            ], 422);
        }

        // Check if 2FA is enabled
        if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => '2FA_NOT_ENABLED',
                    'message' => 'Two-factor authentication is not enabled.',
                ],
            ], 422);
        }

        // Disable 2FA
        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'two_factor_enabled' => false,
            ],
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }
}
