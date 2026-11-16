<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $over = []): User
    {
        return User::unguarded(fn () => User::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'role'      => 'citizen',
        ], $over)));
    }

    private function admin(): User
    {
        return $this->user(['phone' => '+8801700000099', 'role' => 'admin', 'phone_verified_at' => now()]);
    }

    public function test_rows_show_verification_badges(): void
    {
        $admin = $this->admin();
        $this->user(['phone' => '+8801700000031', 'phone_verified_at' => now(), 'display_name' => 'Verified One']);
        $this->user(['phone' => '+8801700000032', 'display_name' => 'Unverified One']);
        $this->user(['phone' => null, 'phone_verified_at' => null, 'display_name' => 'Phoneless One']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee(__('adminusers.verified'))
            ->assertSee(__('adminusers.unverified'))
            ->assertSee(__('adminusers.no_phone'));
    }

    public function test_unverified_filter_shows_only_unverified(): void
    {
        $admin = $this->admin();
        $this->user(['phone' => '+8801700000031', 'phone_verified_at' => now(), 'display_name' => 'Verified One']);
        $this->user(['phone' => '+8801700000032', 'display_name' => 'Unverified One']);

        $this->actingAs($admin)->get('/admin/users?v=unverified')
            ->assertOk()
            ->assertSee('Unverified One')
            ->assertDontSee('Verified One');

        $this->actingAs($admin)->get('/admin/users?v=verified')
            ->assertOk()
            ->assertSee('Verified One')
            ->assertDontSee('Unverified One');

        $this->actingAs($admin)->get('/admin/users?v=none')
            ->assertOk()
            ->assertDontSee('Verified One')
            ->assertDontSee('Unverified One');
    }

    public function test_no_number_filter_lists_phoneless_users(): void
    {
        $admin = $this->admin();
        $this->user(['phone' => null, 'phone_verified_at' => null, 'display_name' => 'Phoneless One']);
        $this->user(['phone' => '+8801700000032', 'display_name' => 'Unverified One']);

        $this->actingAs($admin)->get('/admin/users?v=none')
            ->assertOk()
            ->assertSee('Phoneless One')
            ->assertDontSee('Unverified One');
    }

    public function test_bad_filter_value_falls_back_to_everyone(): void
    {
        $admin = $this->admin();
        $this->user(['phone' => '+8801700000031', 'phone_verified_at' => now(), 'display_name' => 'Verified One']);
        $this->user(['phone' => '+8801700000032', 'display_name' => 'Unverified One']);

        $this->actingAs($admin)->get('/admin/users?v=bogus')
            ->assertOk()
            ->assertSee('Verified One')
            ->assertSee('Unverified One');
    }

    public function test_citizen_cannot_use_the_filter_page(): void
    {
        $c = $this->user(['phone' => '+8801700000033']);
        $this->actingAs($c)->get('/admin/users?v=unverified')->assertForbidden();
    }
}
