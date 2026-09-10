<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_guest_is_redirected_from_users_page()
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_open_users_page()
    {
        $this->actingAs($this->user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_sees_all_users()
    {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 2)
                ->where('currentUserId', $this->admin->id)
            );
    }

    public function test_admin_can_search_users_by_email()
    {
        $this->actingAs($this->admin)
            ->get('/admin/users?search='.$this->user->email)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.email', $this->user->email)
            );
    }

    public function test_admin_can_create_a_user()
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'email' => 'new@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
                'is_admin' => '1',
            ])
            ->assertRedirect('/admin/users');

        $created = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertTrue($created->isAdmin());
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $created->password));
    }

    public function test_created_user_is_not_admin_by_default()
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'email' => 'plain@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertRedirect('/admin/users');

        $this->assertFalse(
            User::query()->where('email', 'plain@example.com')->firstOrFail()->isAdmin()
        );
    }

    public function test_create_rejects_duplicate_email()
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/users', [
                'email' => $this->user->email,
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_non_admin_cannot_create_a_user()
    {
        $this->actingAs($this->user)
            ->post('/admin/users', [
                'email' => 'nope@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
    }

    public function test_admin_can_open_edit_page()
    {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->user->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Edit')
                ->where('user.email', $this->user->email)
                ->where('isSelf', false)
            );
    }

    public function test_admin_can_update_email_and_grant_admin_access()
    {
        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => 'renamed@example.com',
                'is_admin' => '1',
            ])
            ->assertRedirect('/admin/users');

        $this->user->refresh();

        $this->assertSame('renamed@example.com', $this->user->email);
        $this->assertTrue($this->user->isAdmin());
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_update_keeps_password_when_left_blank()
    {
        $original = $this->user->password;

        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => $this->user->email,
                'password' => '',
            ])
            ->assertRedirect('/admin/users');

        $this->assertSame($original, $this->user->refresh()->password);
    }

    public function test_update_changes_password_when_provided()
    {
        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => $this->user->email,
                'password' => 'An0ther-Passw0rd!',
                'password_confirmation' => 'An0ther-Passw0rd!',
            ])
            ->assertRedirect('/admin/users');

        $this->assertTrue(Hash::check('An0ther-Passw0rd!', $this->user->refresh()->password));
    }

    public function test_admin_cannot_remove_own_admin_access()
    {
        $this->actingAs($this->admin)
            ->patchJson("/admin/users/{$this->admin->id}", [
                'email' => $this->admin->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');

        $this->assertTrue($this->admin->refresh()->isAdmin());
    }

    public function test_admin_can_delete_a_user()
    {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->user->id}")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_admin_cannot_delete_themselves()
    {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_delete_a_user()
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)
            ->delete("/admin/users/{$other->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_admin_landing_page_redirects_to_reports()
    {
        $this->actingAs($this->admin)
            ->get('/')
            ->assertRedirect('/admin/issue-reports');
    }

    public function test_regular_user_landing_page_redirects_to_sessions()
    {
        $this->actingAs($this->user)
            ->get('/')
            ->assertRedirect('/game-session/sessions');
    }
}
