<?php

namespace Tests\Feature\Admin;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DenyAdminsTest extends TestCase
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function playerAreaProvider(): array
    {
        return [
            'sessions' => ['/game-session/sessions'],
            'imports' => ['/game-session/imports'],
            'statistics' => ['/statistics'],
            'addon' => ['/addon'],
            'issue report' => ['/issue-report'],
        ];
    }

    #[DataProvider('playerAreaProvider')]
    public function test_admin_is_redirected_from_player_area(string $uri)
    {
        $this->actingAs($this->admin)
            ->get($uri)
            ->assertRedirect('/admin/issue-reports');
    }

    #[DataProvider('playerAreaProvider')]
    public function test_regular_user_still_reaches_player_area(string $uri)
    {
        $this->actingAs($this->user)
            ->get($uri)
            ->assertOk();
    }

    public function test_admin_cannot_import_a_session()
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/game-session/import', ['game_session' => '{"points":[]}'])
            ->assertRedirect('/admin/issue-reports');

        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_submit_an_issue_report()
    {
        $this->actingAs($this->admin)
            ->post('/issue-report', ['message' => 'Admins do not file reports here.'])
            ->assertRedirect('/admin/issue-reports');

        $this->assertDatabaseCount('issue_reports', 0);
    }

    public function test_admin_cannot_open_another_players_session()
    {
        $session = GameSession::factory()->forUser($this->user)->create();

        $this->actingAs($this->admin)
            ->get("/game-session/sessions/{$session->id}")
            ->assertRedirect('/admin/issue-reports');
    }

    public function test_admin_gets_forbidden_on_json_requests()
    {
        $this->actingAs($this->admin)
            ->getJson('/statistics')
            ->assertForbidden();
    }

    public function test_admin_keeps_access_to_the_profile_page()
    {
        $this->actingAs($this->admin)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('profile/Profile'));
    }

    public function test_admin_can_update_their_own_profile()
    {
        $this->actingAs($this->admin)
            ->patch('/profile', ['email' => 'boss@example.com'])
            ->assertRedirect('/profile');

        $this->assertSame('boss@example.com', $this->admin->refresh()->email);
    }
}
