<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_user_sees_account_links_in_menus(): void
    {
        $u = User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone'     => '+8801700000030',
            'role'      => 'citizen',
            'phone_verified_at' => now(),
        ]));

        $this->actingAs($u)->get('/')
            ->assertOk()
            ->assertSee(route('account.phone'))
            ->assertSee(route('account.delete'));
    }

    public function test_guest_sees_no_account_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee(route('account.phone'))
            ->assertDontSee(route('account.delete'));
    }
}
