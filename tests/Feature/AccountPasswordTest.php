<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?string $pw = null): User
    {
        $u = User::create(['phone' => '+8801712345678', 'display_name' => 'T', 'role' => 'citizen']);
        if ($pw !== null) {
            $u->forceFill(['password' => $pw])->save();
        }
        return $u->fresh();
    }

    private function post_pw(User $u, array $data)
    {
        return $this->actingAs($u)->post('/account/password', $data);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/account/password')->assertStatus(302);
        $this->post('/account/password', ['password' => 'newpass123'])->assertStatus(302);
    }

    public function test_page_opens_for_logged_in_user(): void
    {
        $this->actingAs($this->makeUser())->get('/account/password')->assertStatus(200);
    }

    public function test_first_password_needs_no_current(): void
    {
        $u = $this->makeUser();
        $this->post_pw($u, ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newpass123', $u->fresh()->password));
    }

    public function test_change_needs_correct_current(): void
    {
        $u = $this->makeUser('oldpass123');
        $this->post_pw($u, ['current_password' => 'wrong-one', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('pw');
        $this->assertTrue(Hash::check('oldpass123', $u->fresh()->password));
    }

    public function test_change_with_correct_current_works(): void
    {
        $u = $this->makeUser('oldpass123');
        $this->post_pw($u, ['current_password' => 'oldpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newpass123', $u->fresh()->password));
    }

    public function test_short_password_refused(): void
    {
        $u = $this->makeUser();
        $this->post_pw($u, ['password' => 'abc', 'password_confirmation' => 'abc'])->assertSessionHasErrors('pw');
        $this->assertNull($u->fresh()->password);
    }

    public function test_too_long_password_refused(): void
    {
        $u = $this->makeUser();
        $long = str_repeat('a', 101);
        $this->post_pw($u, ['password' => $long, 'password_confirmation' => $long])->assertSessionHasErrors('pw');
        $this->assertNull($u->fresh()->password);
    }

    public function test_mismatch_refused(): void
    {
        $u = $this->makeUser();
        $this->post_pw($u, ['password' => 'newpass123', 'password_confirmation' => 'other12345'])->assertSessionHasErrors('pw');
        $this->assertNull($u->fresh()->password);
    }

    public function test_many_wrong_current_passwords_block_changes(): void
    {
        $u = $this->makeUser('oldpass123');
        for ($i = 0; $i < 5; $i++) {
            $this->post_pw($u, ['current_password' => 'bad' . $i, 'password' => 'newpass123', 'password_confirmation' => 'newpass123']);
        }
        $this->post_pw($u, ['current_password' => 'oldpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('pw');
        $this->assertTrue(Hash::check('oldpass123', $u->fresh()->password));
    }
}