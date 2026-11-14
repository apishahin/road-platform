<?php

namespace Tests\Feature;

use App\Models\FundCampaign;
use App\Models\FundDonation;
use App\Models\User;
use App\Support\Funding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FundingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => $role,
        ]));
    }

    private function campaign(array $over = []): FundCampaign
    {
        return FundCampaign::create(array_merge([
            'title' => 'ZZFUND Test', 'description' => 'desc', 'goal_amount' => 1000,
            'is_active' => true, 'show_donors' => true,
        ], $over));
    }

    private function donate(FundCampaign $c, int $amount, array $over = []): FundDonation
    {
        return FundDonation::create(array_merge([
            'campaign_id' => $c->id, 'amount' => $amount, 'status' => 'paid',
            'gateway' => 'manual', 'is_manual' => true, 'paid_at' => now(),
        ], $over));
    }

    public function test_home_has_no_fund_box_without_active_campaign(): void
    {
        $this->campaign(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="fund-box"', false);
    }

    public function test_home_shows_box_and_donate_button(): void
    {
        $this->campaign();
        $this->get('/')->assertOk()
            ->assertSee('ZZFUND Test')
            ->assertSee('id="fund-donate-btn"', false);
    }

    public function test_home_box_metrics_days_left_and_toggles(): void
    {
        $c = $this->campaign(['ends_at' => now()->addDays(30)->toDateString()]);
        $this->donate($c, 200);
        $this->donate($c, 100);

        // 2 paid donors, 1 campaign, 30 days left, secure-payment note
        $this->get('/')->assertOk()
            ->assertSee(__('issues.fund_donors'))
            ->assertSee(__('issues.fund_projects'))
            ->assertSee(__('issues.fund_days_left', ['count' => \App\Support\Funding::num(30)]))
            ->assertSee(__('issues.fund_secure_note'));

        // every metric can be switched off from the admin
        \App\Support\SiteSections::save(['sections' => ['fund' => ['on' => true, 'm_donors' => false, 'm_projects' => false, 'm_days' => false, 'note' => 'ZZNQ note']]]);
        $this->get('/')->assertOk()
            ->assertDontSee(__('issues.fund_days_left', ['count' => \App\Support\Funding::num(30)]))
            ->assertSee('ZZNQ note');
    }

    public function test_days_left_helper(): void
    {
        $this->assertNull(Funding::daysLeft($this->campaign()));
        $this->assertSame(10, Funding::daysLeft($this->campaign(['ends_at' => now()->addDays(10)->toDateString()])));
        $this->assertSame(0, Funding::daysLeft($this->campaign(['ends_at' => now()->subDays(3)->toDateString()])));
    }

    public function test_admin_can_save_campaign_end_date(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('admin.funding.save'), [
            'title' => 'ZZFUND Dated', 'goal_amount' => 500,
            'ends_at' => now()->addDays(45)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('fund_campaigns', ['title' => 'ZZFUND Dated']);
        $c = FundCampaign::where('title', 'ZZFUND Dated')->first();
        $this->assertNotNull($c->ends_at);
        $this->assertSame(45, Funding::daysLeft($c));
    }

    public function test_donate_button_hides_when_goal_met(): void
    {
        $c = $this->campaign();
        $this->donate($c, 600);
        $this->donate($c, 400);

        $this->get('/')->assertOk()
            ->assertSee('id="fund-box"', false)
            ->assertDontSee('id="fund-donate-btn"', false);
        $this->get('/fund')->assertOk()->assertSee(__('issues.fund_goal_met'));
    }

    public function test_only_paid_donations_count(): void
    {
        $c = $this->campaign();
        $this->donate($c, 999, ['status' => 'pending']);
        $this->donate($c, 999, ['status' => 'failed']);

        $this->assertSame(0, Funding::raised($c));
        $this->get('/')->assertSee('id="fund-donate-btn"', false);
    }

    public function test_donor_names_public_only_when_allowed(): void
    {
        $c = $this->campaign(['show_donors' => true]);
        $this->donate($c, 100, ['donor_name' => 'ZZNAME Shown', 'show_name' => true]);
        $this->donate($c, 100, ['donor_name' => 'ZZNAME Hidden', 'show_name' => false]);
        $this->donate($c, 100, ['donor_name' => 'ZZNAME Secret', 'show_name' => true, 'hide_amount' => true, 'donor_contact' => 'ZZCONTACT 01700']);

        $this->get('/fund')->assertOk()
            ->assertSee('ZZNAME Shown')
            ->assertDontSee('ZZNAME Hidden')
            ->assertSee('ZZNAME Secret')
            ->assertSee(__('issues.fund_amount_hidden'))
            ->assertDontSee('ZZCONTACT');

        $c->update(['show_donors' => false]);
        $this->get('/fund')->assertOk()->assertDontSee('ZZNAME Shown');
    }

    public function test_only_admin_can_manage(): void
    {
        $this->get('/admin/funding')->assertRedirect();

        $citizen = $this->user('citizen');
        $this->actingAs($citizen)->get('/admin/funding')->assertForbidden();
        $this->actingAs($citizen)->post('/admin/funding/save', ['title' => 'X', 'goal_amount' => 100])->assertForbidden();

        $this->actingAs($this->user('admin'))->get('/admin/funding')->assertOk();
    }

    public function test_admin_saves_campaign_with_heads_and_only_one_active(): void
    {
        $old = $this->campaign(['title' => 'ZZOLD']);
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/admin/funding/save', [
            'title' => 'ZZNEW', 'goal_amount' => 5000, 'is_active' => '1', 'show_donors' => '1',
            'heads' => [
                ['name' => 'ZZHEAD Server', 'amount' => 3000],
                ['name' => '', 'amount' => ''],
                ['name' => 'ZZHEAD SMS', 'amount' => 2000],
            ],
        ])->assertRedirect();

        $newC = FundCampaign::where('title', 'ZZNEW')->first();
        $this->assertNotNull($newC);
        $this->assertTrue($newC->is_active);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(2, $newC->heads()->count());

        $this->get('/fund')->assertOk()->assertSee('ZZNEW')->assertSee('ZZHEAD Server');
    }

    public function test_bad_input_rejected_and_manual_donation_counts(): void
    {
        $c = $this->campaign();
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/admin/funding/save', ['title' => 'X', 'goal_amount' => 0])
            ->assertSessionHasErrors('goal_amount');
        $this->actingAs($admin)->post('/admin/funding/manual', ['campaign_id' => $c->id, 'amount' => 5])
            ->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post('/admin/funding/manual', ['campaign_id' => $c->id, 'amount' => 250, 'donor_name' => 'ZZM'])
            ->assertRedirect();
        $this->assertSame(250, Funding::raised($c));

        $d = FundDonation::first();
        $this->actingAs($admin)->post('/admin/funding/donations/' . $d->id . '/void')->assertRedirect();
        $this->assertSame(0, Funding::raised($c));
    }
}
