<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FooterLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '0171' . random_int(1000000, 9999999),
            'role'      => 'admin',
        ]));
    }

    public function test_footer_unchanged_by_default(): void
    {
        $res = $this->get('/');
        $res->assertOk()
            ->assertSee(route('impact'), false)
            ->assertSee(route('leaderboard'), false)
            ->assertSee(__('Report a problem'));
    }

    public function test_admin_page_and_builtin_toggle(): void
    {
        $this->get(route('admin.footer.index'))->assertRedirect('/login');
        $this->actingAs(User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(), 'phone' => '0181' . random_int(1000000, 9999999), 'role' => 'user',
        ])))->get(route('admin.footer.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.footer.index'))
            ->assertOk()->assertSee('data-footer-admin', false);

        // turn off leaderboard + impact only
        $this->actingAs($admin)->post(route('admin.footer.save'), [
            'links' => ['home' => '1', 'issues' => '1', 'dashboard' => '1', 'report' => '1', 'fund' => '1', 'pages' => '1'],
        ])->assertRedirect();

        $this->assertFalse(FooterLinks::builtinOn('leaderboard'));
        $this->assertFalse(FooterLinks::builtinOn('impact'));
        $this->assertTrue(FooterLinks::builtinOn('home'));

        $this->get('/')->assertOk()
            ->assertDontSee(route('leaderboard'), false)
            ->assertDontSee(route('impact'), false)
            ->assertSee(url('/report'), false);
    }

    public function test_custom_links_add_render_delete(): void
    {
        $admin = $this->admin();

        // invalid URLs rejected
        $this->actingAs($admin)->post(route('admin.footer.add'), [
            'label' => 'Bad', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->actingAs($admin)->post(route('admin.footer.add'), [
            'label' => 'আমাদের ব্লগ', 'url' => 'https://blog.example.org',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.footer.add'), [
            'label' => 'FAQ', 'url' => '/p/faq',
        ])->assertRedirect();

        $this->get('/')->assertOk()
            ->assertSee('আমাদের ব্লগ')
            ->assertSee('https://blog.example.org', false)
            ->assertSee('/p/faq', false);

        $this->actingAs($admin)->post(route('admin.footer.delete'), ['index' => 0]);
        $this->assertCount(1, FooterLinks::custom());
        $this->get('/')->assertDontSee('আমাদের ব্লগ');
    }
}
