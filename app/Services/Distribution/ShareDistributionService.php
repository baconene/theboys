<?php

namespace App\Services\Distribution;

use App\Models\Shareholder;

/**
 * Allocates a distributable amount to active shareholders by ownership %,
 * and computes the company retained-earnings remainder.
 */
class ShareDistributionService
{
    /**
     * @return array{
     *   members: array<int, array{shareholder_id:int, name:string, percentage:float, amount:float}>,
     *   members_total: float,
     *   members_percentage: float,
     *   company_amount: float,
     *   company_percentage: float
     * }
     */
    public function allocate(float $distributable, ?int $onlyShareholderId = null): array
    {
        $shareholders = Shareholder::active()
            ->when($onlyShareholderId, fn ($q) => $q->where('id', $onlyShareholderId))
            ->orderByDesc('ownership_percentage')
            ->get();

        $members = [];
        $membersTotal = 0.0;
        $pctTotal = 0.0;

        foreach ($shareholders as $s) {
            $pct = (float) $s->ownership_percentage;
            $amount = round($distributable * ($pct / 100), 2);
            $membersTotal = round($membersTotal + $amount, 2);
            $pctTotal += $pct;
            $members[] = [
                'shareholder_id' => $s->id,
                'name'           => $s->name,
                'percentage'     => $pct,
                'amount'         => $amount,
            ];
        }

        // Company keeps whatever isn't allocated to members (always vs the FULL
        // distributable, even when filtering to a single shareholder for preview).
        $allMembersPct = Shareholder::totalOwnership();
        $companyPct = max(0, round(100 - $allMembersPct, 2));
        $companyAmount = round($distributable * ($companyPct / 100), 2);

        return [
            'members'            => $members,
            'members_total'      => $membersTotal,
            'members_percentage' => round($pctTotal, 2),
            'company_amount'     => $companyAmount,
            'company_percentage' => $companyPct,
        ];
    }
}
