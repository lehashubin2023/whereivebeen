<?php

namespace Tests\Feature\Admin;

use App\Models\GameSession;
use App\Models\IssueReport;
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
                ->where('search', null)
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

    public function test_search_by_an_empty_string_is_rejected()
    {
        $this->actingAs($this->admin)
            ->from('/admin/users')
            ->get('/admin/users?search=')
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('search');
    }

    public function test_search_by_whitespace_only_is_rejected()
    {
        $this->actingAs($this->admin)
            ->from('/admin/users')
            ->get('/admin/users?search=%20%20')
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('search');
    }

    public function test_admin_can_create_a_user()
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'email' => 'new@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertRedirect('/admin/users');

        $created = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertFalse($created->isAdmin());
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $created->password));
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

    public function test_create_rejects_a_duplicate_email_in_another_case()
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/users', [
                'email' => strtoupper($this->user->email),
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
                ->where('user.is_main_admin', false)
                ->where('isSelf', false)
                ->where('canAssignAdmin', false)
            );
    }

    public function test_admin_can_update_email()
    {
        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => 'renamed@example.com',
            ])
            ->assertRedirect('/admin/users');

        $this->user->refresh();

        $this->assertSame('renamed@example.com', $this->user->email);
        $this->assertFalse($this->user->isAdmin());
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_update_rejects_an_email_taken_by_somebody_else()
    {
        $this->actingAs($this->admin)
            ->patchJson("/admin/users/{$this->user->id}", [
                'email' => $this->admin->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_update_keeps_the_email_of_the_edited_user()
    {
        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => $this->user->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertNotNull($this->user->refresh()->email_verified_at);
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

    public function test_admin_cannot_change_their_own_account_type()
    {
        $this->actingAs($this->admin)
            ->patchJson("/admin/users/{$this->admin->id}", [
                'email' => $this->admin->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');

        $this->assertTrue($this->admin->refresh()->isAdmin());
    }

    public function test_admin_can_delete_a_user_after_confirming_the_email()
    {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->user->id}", [
                'confirmation' => $this->user->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_delete_requires_a_confirmation()
    {
        $this->actingAs($this->admin)
            ->deleteJson("/admin/users/{$this->user->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_delete_rejects_a_confirmation_of_another_email()
    {
        $this->actingAs($this->admin)
            ->deleteJson("/admin/users/{$this->user->id}", [
                'confirmation' => 'someone-else@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_delete_removes_every_trace_of_the_user()
    {
        $session = GameSession::factory()->forUser($this->user)->create();
        $report = IssueReport::factory()->forUser($this->user)->create();

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->user->id}", [
                'confirmation' => $this->user->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        $this->assertDatabaseMissing('game_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('issue_reports', ['id' => $report->id]);
    }

    public function test_admin_cannot_delete_themselves()
    {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->admin->id}", [
                'confirmation' => $this->admin->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_delete_a_user()
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)
            ->delete("/admin/users/{$other->id}", [
                'confirmation' => $other->email,
            ])
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
