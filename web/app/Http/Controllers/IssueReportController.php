<?php

namespace App\Http\Controllers;

use App\Actions\IssueReport\CreateIssueReport;
use App\DTOs\IssueReport\CreateIssueReportDTO;
use App\Http\Requests\IssueReport\StoreIssueReportRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware(['auth', 'verified', 'deny-admins'])]
#[Group(prefix: 'issue-report', as: 'issue-report.')]
class IssueReportController extends Controller
{
    public function __construct(
        private readonly CreateIssueReport $createIssueReport,
    ) {}

    #[Get(uri: '', name: 'create')]
    public function create(): Response
    {
        return Inertia::render('issue-report/Create');
    }

    #[Post(uri: '', name: 'store', middleware: 'throttle:issue-report')]
    public function store(StoreIssueReportRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->createIssueReport->exec(
            CreateIssueReportDTO::fromArray($request->validated()),
            $user,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks! Your report has been submitted.'),
        ]);

        return to_route('issue-report.create');
    }
}
