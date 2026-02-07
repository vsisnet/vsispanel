<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Http\Controllers;

use App\Modules\Reseller\Models\ResellerBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResellerBrandingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $branding = ResellerBranding::where('reseller_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $branding,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'custom_css' => 'nullable|string|max:10000',
            'support_email' => 'nullable|email|max:255',
            'support_url' => 'nullable|url|max:255',
            'nameservers' => 'nullable|array|max:4',
            'nameservers.*' => 'string|max:255',
        ]);

        $branding = ResellerBranding::updateOrCreate(
            ['reseller_id' => $user->id],
            $validated,
        );

        return response()->json([
            'success' => true,
            'data' => $branding,
            'message' => 'Branding updated successfully.',
        ]);
    }

    /**
     * Upload logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,svg|max:2048',
        ]);

        $path = $request->file('logo')->store("reseller/{$user->id}", 'public');

        $branding = ResellerBranding::updateOrCreate(
            ['reseller_id' => $user->id],
            ['logo_path' => $path],
        );

        return response()->json([
            'success' => true,
            'data' => ['logo_path' => $path],
            'message' => 'Logo uploaded.',
        ]);
    }
}
