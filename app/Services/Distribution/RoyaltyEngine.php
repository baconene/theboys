<?php

namespace App\Services\Distribution;

use App\Models\RoyaltyRule;

/**
 * Computes royalties from product net sales using active royalty_rules.
 * royalty_amount = Σ (product_net_sales × rate) for every rule effective in
 * the period, resolved at product OR category scope. Supports multiple
 * recipients and produces per-product / per-category analytics.
 */
class RoyaltyEngine
{
    public function __construct(private SalesAggregateService $sales) {}

    /**
     * @return array{
     *   total: float,
     *   by_recipient: array<int, array{recipient_name:string, shareholder_id:?int, amount:float}>,
     *   by_product: array<int, array{name:string, net_sales:float, royalty:float, rate:float}>,
     *   by_category: array<int, array{category_id:int, royalty:float}>
     * }
     */
    public function compute(string $start, string $end, ?int $categoryId = null, ?int $productId = null): array
    {
        $productSales = $this->sales->productNetSales($start, $end, $categoryId, $productId);
        if (empty($productSales)) {
            return ['total' => 0.0, 'by_recipient' => [], 'by_product' => [], 'by_category' => []];
        }

        $rules = RoyaltyRule::effectiveDuring($start, $end)->get();
        if ($rules->isEmpty()) {
            return ['total' => 0.0, 'by_recipient' => [], 'by_product' => [], 'by_category' => []];
        }

        $productRules  = $rules->where('scope', 'product')->groupBy('product_id');
        $categoryRules = $rules->where('scope', 'category')->groupBy('category_id');

        $total       = 0.0;
        $byRecipient = [];   // keyed by recipient_name|shareholder_id
        $byProduct   = [];
        $byCategory  = [];

        foreach ($productSales as $ps) {
            $applicable = collect();

            if (isset($productRules[$ps['product_id']])) {
                $applicable = $applicable->merge($productRules[$ps['product_id']]);
            }
            if (isset($categoryRules[$ps['category_id']])) {
                $applicable = $applicable->merge($categoryRules[$ps['category_id']]);
            }
            if ($applicable->isEmpty()) {
                continue;
            }

            $productRoyalty = 0.0;
            $rateSum = 0.0;
            foreach ($applicable as $rule) {
                $amount = round($ps['net_sales'] * ((float) $rule->royalty_percentage / 100), 2);
                $productRoyalty += $amount;
                $rateSum += (float) $rule->royalty_percentage;
                $total += $amount;

                $key = ($rule->shareholder_id ?? 'x') . '|' . $rule->recipient_name;
                if (! isset($byRecipient[$key])) {
                    $byRecipient[$key] = [
                        'recipient_name' => $rule->recipient_name,
                        'shareholder_id' => $rule->shareholder_id,
                        'amount'         => 0.0,
                    ];
                }
                $byRecipient[$key]['amount'] = round($byRecipient[$key]['amount'] + $amount, 2);

                $cid = (int) $ps['category_id'];
                $byCategory[$cid] = round(($byCategory[$cid] ?? 0) + $amount, 2);
            }

            if ($productRoyalty > 0) {
                $byProduct[] = [
                    'name'      => $ps['name'],
                    'net_sales' => $ps['net_sales'],
                    'royalty'   => round($productRoyalty, 2),
                    'rate'      => $rateSum,
                ];
            }
        }

        usort($byProduct, fn ($a, $b) => $b['royalty'] <=> $a['royalty']);

        return [
            'total'        => round($total, 2),
            'by_recipient' => array_values($byRecipient),
            'by_product'   => $byProduct,
            'by_category'  => collect($byCategory)->map(fn ($v, $k) => ['category_id' => $k, 'royalty' => $v])->values()->all(),
        ];
    }
}
