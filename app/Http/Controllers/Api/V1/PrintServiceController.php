<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrintServiceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PrintServiceController extends Controller
{
    public function getSettings(): JsonResponse
    {
        $this->adminOnly();

        return response()->json(PrintServiceSetting::getSetting());
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->adminOnly();

        $data = $request->validate([
            'print_service_url'   => 'required|string|max:255',
            'print_paper_width'   => 'required|integer|in:32,48',
            'print_store_name'    => 'nullable|string|max:255',
            'print_store_address' => 'nullable|string|max:255',
            'print_store_phone'   => 'nullable|string|max:100',
            'print_footer'        => 'nullable|string|max:255',
            'print_auto_print'    => 'boolean',
            'print_enabled'       => 'boolean',
            'print_channel'       => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
        ]);

        // Default to "orders" if left blank
        $data['print_channel'] = ($data['print_channel'] ?? '') ?: 'orders';

        $setting = PrintServiceSetting::getSetting();
        $setting->update($data);

        return response()->json($setting->fresh());
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->adminOnly();

        $request->validate([
            'url' => 'required|string|max:255',
        ]);

        try {
            $response = Http::timeout(5)->get(
                rtrim($request->input('url'), '/') . '/status'
            );

            if ($response->successful()) {
                $body = $response->json();
                return response()->json([
                    'reachable'         => true,
                    'printer_connected' => $body['printer_connected'] ?? null,
                    'status'            => $body['status'] ?? null,
                ]);
            }

            return response()->json([
                'reachable' => false,
                'error'     => 'HTTP ' . $response->status(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'reachable' => false,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function adminOnly(): void
    {
        if (! auth()->user()?->hasAnyRole('admin')) {
            abort(403, 'Admin only');
        }
    }
}
