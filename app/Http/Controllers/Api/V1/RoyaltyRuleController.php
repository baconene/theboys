<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoyaltyRule;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoyaltyRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->adminOnly();

        $rules = RoyaltyRule::with(['product:id,name', 'category:id,name', 'shareholder:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->adminOnly();
        $data = $this->validateData($request);

        $rule = RoyaltyRule::create($data);
        AuditLogger::record('royalty_rule.created', $rule, null, $rule->toArray(), "Added royalty rule for {$rule->recipient_name}");

        return response()->json($rule->load(['product:id,name', 'category:id,name', 'shareholder:id,name']), 201);
    }

    public function update(Request $request, RoyaltyRule $royaltyRule): JsonResponse
    {
        $this->adminOnly();
        $data = $this->validateData($request);

        $old = $royaltyRule->toArray();
        $royaltyRule->update($data);
        AuditLogger::record('royalty_rule.updated', $royaltyRule, $old, $royaltyRule->toArray(), "Updated royalty rule for {$royaltyRule->recipient_name}");

        return response()->json($royaltyRule->load(['product:id,name', 'category:id,name', 'shareholder:id,name']));
    }

    public function destroy(RoyaltyRule $royaltyRule): JsonResponse
    {
        $this->adminOnly();

        $old = $royaltyRule->toArray();
        AuditLogger::record('royalty_rule.deleted', $royaltyRule, $old, null, "Removed royalty rule for {$royaltyRule->recipient_name}");
        $royaltyRule->delete();

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'scope'              => 'required|in:product,category',
            'product_id'         => 'nullable|required_if:scope,product|exists:products,id',
            'category_id'        => 'nullable|required_if:scope,category|exists:categories,id',
            'recipient_name'     => 'required|string|max:120',
            'shareholder_id'     => 'nullable|exists:shareholders,id',
            'royalty_percentage' => 'required|numeric|min:0|max:100',
            'effective_date'     => 'required|date',
            'expiration_date'    => 'nullable|date|after_or_equal:effective_date',
            'is_active'          => 'boolean',
        ]);

        // Null the non-scope id so a product rule never carries a stale category_id
        if ($data['scope'] === 'product') {
            $data['category_id'] = null;
        } else {
            $data['product_id'] = null;
        }

        return $data;
    }

    private function adminOnly(): void
    {
        if (! auth()->user()?->hasAnyRole('admin')) {
            abort(403, 'Admin only');
        }
    }
}
