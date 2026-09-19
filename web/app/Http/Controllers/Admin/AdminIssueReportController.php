<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueReport\UpdateIssueReportStatus;
use App\Enums\IssueReport\IssueReportStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueReport\UpdateIssueReportStatusRequest;
use App\Models\IssueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;

#[Middleware(['auth', 'can:view-issue-reports'])]
#[Group(prefix: 'admin/issue-reports', as: 'admin.issue-reports.')]
class AdminIssueReportController extends Controller
{
    public function __construct(
        private readonly UpdateIssueReportStatus $updateIssueReportStatus,
    ) {}

    #[Get(uri: '', name: 'index')]
    public function index(Request $request): Response
    {
        $status = IssueReportStatusEnum::tryFrom((string) $request->query('status'));

        $reports = IssueReport::query()
            ->with('user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate((int) config('pagination.per_page'))
            ->withQueryString()
            ->through(fn (IssueReport $report) => [
                'id' => $report->id,
                'message' => $report->message,
                'status' => $report->status->value,
                'user_email' => $report->user?->email,
                'created_at' => $report->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/IssueReports', [
            'reports' => $reports,
            'status' => $status?->value,
        ]);
    }

    #[Patch(uri: '{issueReport}/status', name: 'status.update')]
    public function updateStatus(
        UpdateIssueReportStatusRequest $request,
        IssueReport $issueReport,
    ): RedirectResponse {
        $this->updateIssueReportStatus->exec($issueReport, $request->status());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report status updated.'),
        ]);

        return back();
    }
}
