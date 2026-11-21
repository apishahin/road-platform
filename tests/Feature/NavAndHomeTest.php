<?php

namespace Tests\Feature;

use App\Models\AdminArea;
use App\Models\ModeratorArea;
use App\Models\User;
use App\Support\AppearanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavAndHomeTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): static
    {
        $n = ['admin' => '11', 'moderator' => '12', 'volunteer' => '13', 'citizen' => '14'][$role];
        $u = User::create(['phone' => '+88019000000' . $n, 'display_name' => 'T', 'role' => $role]);

        return $this->actingAs($u);
    }

    public function test_guest_home_has_map_list_and_no_admin_links(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="home-map"', false)
            ->assertSee('id="issue-list"', false)
            ->assertSee('id="locate-btn"', false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(__('issues.home_repaired_title'));
    }

    public function test_admin_menu_has_all_groups(): void
    {
        $r = $this->as('admin')->get('/')->assertOk();
        foreach (['admin.work-center', 'admin.contacts.index', 'admin.applications.index', 'admin.users.index', 'admin.moderators.index', 'admin.loginsettings.index', 'admin.mail.index', 'admin.storage.index', 'admin.site.index'] as $name) {
            $r->assertSee(route($name), false);
        }
    }

    public function test_moderator_sees_only_moderation_links(): void
    {
        $this->as('moderator')->get('/')->assertOk()
            ->assertSee(route('admin.work-center'), false)
            ->assertSee(route('admin.contacts.index'), false)
            ->assertDontSee(route('admin.moderation.index'), false)
            ->assertDontSee(route('admin.issues.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.smsgateways.index'), false);
    }

    public function test_mobile_staff_menu_has_grouped_touch_controls(): void
    {
        $response = $this->as('admin')->get('/')->assertOk()
            ->assertSee('id="mobile-site-menu"', false)
            ->assertSee('class="rp-mobile-staff"', false)
            ->assertSee('class="rp-mobile-staff-heading"', false)
            ->assertSee('class="rp-mobile-staff-group"', false)
            ->assertSee('class="rp-mobile-staff-link', false)
            ->assertSee('class="rp-mobile-account"', false);

        $response->assertSee('<details', false)
            ->assertSee(route('admin.storage.index'), false)
            ->assertSee(route('admin.work-center'), false)
            ->assertDontSee(route('admin.moderation.index'), false);
    }

    public function test_assigned_volunteer_gets_the_mobile_review_controls(): void
    {
        $volunteer = User::create(['phone' => '+8801900000093', 'display_name' => 'Volunteer', 'role' => 'volunteer']);
        $area = AdminArea::unguarded(fn () => AdminArea::create(['level' => 'division', 'name_bn' => 'ঢাকা']));
        ModeratorArea::create(['user_id' => $volunteer->id, 'admin_area_id' => $area->id]);

        $this->actingAs($volunteer)->get('/')->assertOk()
            ->assertSee('class="rp-mobile-staff"', false)
            ->assertSee(route('admin.work-center'), false)
            ->assertDontSee(route('admin.moderation.index'), false)
            ->assertDontSee(route('verify.queue'), false)
            ->assertDontSee(route('admin.users.index'), false);
    }

    public function test_citizen_sees_apply_link_but_no_admin_links(): void
    {
        $this->as('citizen')->get('/')->assertOk()
            ->assertSee(route('volunteer.apply'), false)
            ->assertDontSee(route('admin.work-center'), false)
            ->assertDontSee(route('admin.issues.index'), false);
    }

    public function test_site_name_is_used_in_og_tag(): void
    {
        $this->get('/')->assertSee('property="og:site_name" content="' . e(AppearanceSettings::siteName()) . '"', false);
    }

    public function test_privacy_note_is_shown(): void
    {
        $this->get('/')->assertSee(__('issues.home_privacy'));
    }

    public function test_new_bangla_strings_are_real_utf8(): void
    {
        app()->setLocale('bn');
        foreach (['nav_settings', 'home_repaired_title', 'home_privacy'] as $k) {
            $t = __('issues.' . $k);
            $this->assertNotSame('issues.' . $k, $t);
            $this->assertTrue(mb_check_encoding($t, 'UTF-8'));
            $this->assertSame(1, preg_match('/\p{Bengali}/u', $t));
        }
        app()->setLocale('en');
        $this->assertSame('Settings', __('issues.nav_settings'));
    }
}