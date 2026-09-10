<?php

namespace Tests\Feature\IssueReport;

use App\Enums\IssueReport\IssueReportStatusEnum;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IssueReportTest extends TestCase
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

    public function test_guest_is_redirected_from_report_page()
    {
        $this->get('/issue-report')->assertRedirect('/login');
    }

    public function test_report_page_renders()
    {
        $this->actingAs($this->user)
            ->get('/issue-report')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('issue-report/Create'));
    }

    public function test_report_is_stored_with_new_status()
    {
        $this->actingAs($this->user)
            ->post('/issue-report', ['message' => 'The map does not load for my session.'])
            ->assertRedirect('/issue-report');

        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $this->user->id,
            'message' => 'The map does not load for my session.',
            'status' => IssueReportStatusEnum::NEW->value,
        ]);
    }

    public function test_report_requires_a_message()
    {
        $this->actingAs($this->user)
            ->postJson('/issue-report', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('issue_reports', 0);
    }

    public function test_report_message_must_be_long_enough()
    {
        $this->actingAs($this->user)
            ->postJson('/issue-report', ['message' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_non_admin_cannot_open_admin_page()
    {
        $this->actingAs($this->user)
            ->get('/admin/issue-reports')
            ->assertForbidden();
    }

    public function test_admin_sees_reports_of_all_users()
    {
        IssueReport::factory()->forUser($this->user)->create();
        IssueReport::factory()->forUser($this->admin)->create();

        $this->actingAs($this->admin)
            ->get('/admin/issue-reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/IssueReports')
                ->has('reports.data', 2)
                ->where('status', null)
            );
    }

    public function test_admin_can_filter_by_status()
    {
        IssueReport::factory()->forUser($this->user)->create();
        IssueReport::factory()->forUser($this->user)->resolved()->create();

        $this->actingAs($this->admin)
            ->get('/admin/issue-reports?status=new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/IssueReports')
                ->has('reports.data', 1)
                ->where('reports.data.0.status', 'new')
                ->where('status', 'new')
            );
    }

    public function test_admin_can_resolve_a_report()
    {
        $report = IssueReport::factory()->forUser($this->user)->create();

        $this->actingAs($this->admin)
            ->from('/admin/issue-reports')
            ->patch("/admin/issue-reports/{$report->id}/status", ['status' => 'resolved'])
            ->assertRedirect('/admin/issue-reports');

        $this->assertDatabaseHas('issue_reports', [
            'id' => $report->id,
            'status' => IssueReportStatusEnum::RESOLVED->value,
        ]);
    }

    public function test_non_admin_cannot_change_status()
    {
        $report = IssueReport::factory()->forUser($this->user)->create();

        $this->actingAs($this->user)
            ->patch("/admin/issue-reports/{$report->id}/status", ['status' => 'resolved'])
            ->assertForbidden();

        $this->assertDatabaseHas('issue_reports', [
            'id' => $report->id,
            'status' => IssueReportStatusEnum::NEW->value,
        ]);
    }

    public function test_status_must_be_a_valid_value()
    {
        $report = IssueReport::factory()->forUser($this->user)->create();

        $this->actingAs($this->admin)
            ->patchJson("/admin/issue-reports/{$report->id}/status", ['status' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
