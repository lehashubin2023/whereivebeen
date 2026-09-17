<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MainAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $mainAdmin;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->mainAdmin = User::factory()->admin()->create([
            'email' => config('admin.main_email'),
        ]);
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
    }

    public function test_only_the_main_admin_is_flagged_as_such()
    {
        $this->assertTrue($this->mainAdmin->isMainAdmin());
        $this->assertFalse($this->admin->isMainAdmin());
        $this->assertFalse($this->user->isMainAdmin());
    }

    public function test_main_admin_can_create_another_admin()
    {
        $this->actingAs($this->mainAdmin)
            ->post('/admin/users', [
                'email' => 'second@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
                'is_admin' => '1',
            ])
            ->assertRedirect('/admin/users');

        $this->assertTrue(
            User::query()->where('email', 'second@example.com')->firstOrFail()->isAdmin()
        );
    }

    public function test_regular_admin_cannot_create_another_admin()
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/users', [
                'email' => 'second@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
                'is_admin' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_create_page_hides_the_admin_switch_from_regular_admins()
    {
        $this->actingAs($this->admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canAssignAdmin', false));

        $this->actingAs($this->mainAdmin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canAssignAdmin', true));
    }

    public function test_main_admin_can_change_the_account_type_of_another_admin()
    {
        $this->actingAs($this->mainAdmin)
            ->patch("/admin/users/{$this->admin->id}", [
                'email' => $this->admin->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertFalse($this->admin->refresh()->isAdmin());
    }

    public function test_main_admin_can_grant_admin_access()
    {
        $this->actingAs($this->mainAdmin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => $this->user->email,
                'is_admin' => '1',
            ])
            ->assertRedirect('/admin/users');

        $this->assertTrue($this->user->refresh()->isAdmin());
    }

    public function test_regular_admin_cannot_edit_another_admin()
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->get("/admin/users/{$other->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch("/admin/users/{$other->id}", [
                'email' => 'taken-over@example.com',
            ])
            ->assertForbidden();

        $this->assertSame($other->email, $other->refresh()->email);
    }

    public function test_regular_admin_cannot_grant_admin_access()
    {
        $this->actingAs($this->admin)
            ->patchJson("/admin/users/{$this->user->id}", [
                'email' => $this->user->email,
                'is_admin' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');

        $this->assertFalse($this->user->refresh()->isAdmin());
    }

    public function test_regular_admin_can_still_edit_a_regular_user()
    {
        $this->actingAs($this->admin)
            ->patch("/admin/users/{$this->user->id}", [
                'email' => 'renamed@example.com',
            ])
            ->assertRedirect('/admin/users');

        $this->assertSame('renamed@example.com', $this->user->refresh()->email);
    }

    public function test_regular_admin_cannot_reach_the_main_admin()
    {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->mainAdmin->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->mainAdmin->id}", [
                'confirmation' => $this->mainAdmin->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->mainAdmin->id]);
    }

    public function test_main_admin_cannot_change_their_own_email()
    {
        $this->actingAs($this->mainAdmin)
            ->patchJson("/admin/users/{$this->mainAdmin->id}", [
                'email' => 'moved@example.com',
                'is_admin' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(config('admin.main_email'), $this->mainAdmin->refresh()->email);
    }

    public function test_main_admin_cannot_drop_their_own_admin_access()
    {
        $this->actingAs($this->mainAdmin)
            ->patchJson("/admin/users/{$this->mainAdmin->id}", [
                'email' => $this->mainAdmin->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');

        $this->assertTrue($this->mainAdmin->refresh()->isAdmin());
    }

    public function test_main_admin_can_change_their_own_password()
    {
        $this->actingAs($this->mainAdmin)
            ->patch("/admin/users/{$this->mainAdmin->id}", [
                'email' => $this->mainAdmin->email,
                'is_admin' => '1',
                'password' => 'An0ther-Passw0rd!',
                'password_confirmation' => 'An0ther-Passw0rd!',
            ])
            ->assertRedirect('/admin/users');
    }

    public function test_main_admin_can_delete_another_admin()
    {
        $this->actingAs($this->mainAdmin)
            ->delete("/admin/users/{$this->admin->id}", [
                'confirmation' => $this->admin->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', ['id' => $this->admin->id]);
    }

    public function test_regular_admin_cannot_delete_another_admin()
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$other->id}", [
                'confirmation' => $other->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_main_admin_cannot_delete_themselves()
    {
        $this->actingAs($this->mainAdmin)
            ->delete("/admin/users/{$this->mainAdmin->id}", [
                'confirmation' => $this->mainAdmin->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->mainAdmin->id]);
    }

    public function test_main_admin_cannot_change_their_email_from_the_profile_page()
    {
        $this->actingAs($this->mainAdmin)
            ->patchJson('/profile', ['email' => 'moved@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(config('admin.main_email'), $this->mainAdmin->refresh()->email);
    }

    public function test_main_admin_cannot_delete_their_own_account_from_the_profile_page()
    {
        $this->actingAs($this->mainAdmin)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->mainAdmin->id]);
    }

    public function test_index_marks_the_main_admin_and_hides_forbidden_actions()
    {
        $this->actingAs($this->admin)
            ->get('/admin/users?search='.config('admin.main_email'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.is_main_admin', true)
                ->where('users.data.0.can_edit', false)
                ->where('users.data.0.can_delete', false)
            );
    }
}
