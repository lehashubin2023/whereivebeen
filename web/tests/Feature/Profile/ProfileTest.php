<?php

namespace Tests\Feature\Profile;

use App\Models\GameSession;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->user = User::factory()->create();
    }

    private function mainAdmin(): User
    {
        return User::factory()->admin()->create([
            'email' => config('admin.main_email'),
        ]);
    }

    public function test_guest_is_redirected_from_the_profile_page()
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_profile_page_renders()
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Profile')
                ->where('isMainAdmin', false)
            );
    }

    public function test_profile_page_marks_the_main_admin()
    {
        $this->actingAs($this->mainAdmin())
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('isMainAdmin', true));
    }

    public function test_user_can_change_their_email()
    {
        $this->actingAs($this->user)
            ->patch('/profile', ['email' => 'renamed@example.com'])
            ->assertRedirect('/profile');

        $this->user->refresh();

        $this->assertSame('renamed@example.com', $this->user->email);
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_admin_can_change_their_email()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/profile', ['email' => 'boss@example.com'])
            ->assertRedirect('/profile');

        $this->assertSame('boss@example.com', $admin->refresh()->email);
    }

    public function test_main_admin_cannot_change_their_email()
    {
        $mainAdmin = $this->mainAdmin();

        $this->actingAs($mainAdmin)
            ->patchJson('/profile', ['email' => 'moved@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(config('admin.main_email'), $mainAdmin->refresh()->email);
    }

    public function test_main_admin_can_resubmit_their_own_email()
    {
        $mainAdmin = $this->mainAdmin();

        $this->actingAs($mainAdmin)
            ->patch('/profile', ['email' => $mainAdmin->email])
            ->assertRedirect('/profile');
    }

    public function test_email_must_be_unique()
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)
            ->patchJson('/profile', ['email' => $other->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertNotSame($other->email, $this->user->refresh()->email);
    }

    public function test_email_is_required_and_valid()
    {
        $this->actingAs($this->user)
            ->patchJson('/profile', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->actingAs($this->user)
            ->patchJson('/profile', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_keeping_the_same_email_leaves_the_verification_alone()
    {
        $this->actingAs($this->user)
            ->patch('/profile', ['email' => $this->user->email])
            ->assertRedirect('/profile');

        $this->assertNotNull($this->user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $this->actingAs($this->user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        $this->assertFalse(Auth::check());
    }

    public function test_account_deletion_requires_the_current_password()
    {
        $this->actingAs($this->user)
            ->deleteJson('/profile', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_account_deletion_without_a_password_is_rejected()
    {
        $this->actingAs($this->user)
            ->deleteJson('/profile')
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_account_deletion_removes_every_trace_of_the_user()
    {
        $session = GameSession::factory()->forUser($this->user)->create();
        $report = IssueReport::factory()->forUser($this->user)->create();

        $this->actingAs($this->user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        $this->assertDatabaseMissing('game_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('issue_reports', ['id' => $report->id]);
    }

    public function test_main_admin_cannot_delete_their_own_account()
    {
        $mainAdmin = $this->mainAdmin();

        $this->actingAs($mainAdmin)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $mainAdmin->id]);
    }
}
