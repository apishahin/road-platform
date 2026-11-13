<?php

namespace App\Support;

use App\Models\FundCampaign;
use App\Models\FundDonation;
use Illuminate\Support\Collection;

/** Public funding: one active campaign, money counted only from paid donations. */
class Funding
{
    public static function active(): ?FundCampaign
    {
        try {
            return FundCampaign::where('is_active', true)->latest('id')->first();
        } catch (\Throwable $e) {
            return null; // table not migrated yet: never break the home page
        }
    }

    public static function raised(FundCampaign $c): int
    {
        return (int) FundDonation::where('campaign_id', $c->id)->where('status', 'paid')->sum('amount');
    }

    public static function canDonate(FundCampaign $c, ?int $raised = null): bool
    {
        $raised ??= self::raised($c);

        return $c->is_active && $c->goal_amount > 0 && $raised < $c->goal_amount;
    }

    public static function percent(int $raised, int $goal): int
    {
        if ($goal <= 0) {
            return 0;
        }

        return (int) min(100, floor($raised * 100 / $goal));
    }

    public static function num($n): string
    {
        $s = number_format((int) $n);
        if (app()->getLocale() !== 'bn') {
            return $s;
        }
        $map = [];
        for ($i = 0; $i < 10; $i++) {
            $map[(string) $i] = mb_chr(0x09E6 + $i, 'UTF-8');
        }

        return strtr($s, $map);
    }

    /** Names only when the campaign allows it AND the donor ticked "show my name". */
    public static function publicDonors(FundCampaign $c, int $limit = 200): Collection
    {
        if (! $c->show_donors) {
            return collect();
        }

        return FundDonation::where('campaign_id', $c->id)
            ->where('status', 'paid')
            ->where('show_name', true)
            ->whereNotNull('donor_name')
            ->latest('paid_at')
            ->limit($limit)
            ->get(['donor_name', 'amount', 'hide_amount', 'paid_at']);
    }
}
