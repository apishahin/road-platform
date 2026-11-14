<?php

namespace Database\Seeders;

use App\Models\FundCampaign;
use App\Models\FundDonation;
use App\Models\FundExpense;
use App\Models\FundHead;
use App\Support\PaySettings;
use Illuminate\Database\Seeder;

/**
 * Optional demo content so the /fund page can be seen in action locally.
 * Run: php artisan db:seed --class=FundDemoSeeder
 * Safe to run repeatedly (does nothing if the demo campaign exists).
 */
class FundDemoSeeder extends Seeder
{
    public function run(): void
    {
        $c = FundCampaign::firstOrCreate(
            ['title' => 'Road Repair Fund'],
            [
                'description' => 'Help us fix the worst roads first. Every taka goes to verified repairs.',
                'goal_amount' => 50000,
                'is_active'   => true,
                'show_donors' => true,
            ]
        );

        if (! $c->wasRecentlyCreated) {
            return;
        }

        $heads = [];
        $sort = 0;
        foreach ([['Pothole materials', 20000], ['Equipment rental', 15000], ['Volunteer transport', 5000], ['Safety gear', 10000]] as $h) {
            $heads[] = FundHead::create(['campaign_id' => $c->id, 'name' => $h[0], 'amount' => $h[1], 'sort' => $sort++]);
        }

        FundDonation::create(['campaign_id' => $c->id, 'amount' => 1500, 'status' => 'paid', 'gateway' => 'manual', 'is_manual' => true, 'donor_name' => 'Rahim Uddin', 'show_name' => true, 'paid_at' => now()->subDays(2)]);
        FundDonation::create(['campaign_id' => $c->id, 'amount' => 3000, 'status' => 'paid', 'gateway' => 'manual', 'is_manual' => true, 'donor_name' => 'Karim Bhai', 'show_name' => true, 'hide_amount' => true, 'paid_at' => now()->subDay()]);

        FundExpense::create(['campaign_id' => $c->id, 'head_id' => $heads[0]->id, 'amount' => 800, 'spent_on' => now()->subDay()->toDateString(), 'description' => 'Cold mix asphalt - 2 bags']);

        // Sandbox gateway so the donate form is visible for testing.
        PaySettings::save(['up_enabled' => true, 'up_base_url' => 'https://sandbox.uddoktapay.com', 'up_api_key' => '982d381360a69d419689740d9f2e26ce36fb7a50']);
    }
}
