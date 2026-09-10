<?php

namespace App\Http\Controllers;

use App\Actions\GameSession\BuildUserStatistics;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;

#[Middleware(['auth', 'deny-admins'])]
class StatisticsController extends Controller
{
    public function __construct(
        private readonly BuildUserStatistics $buildUserStatistics,
    ) {}

    #[Get('statistics', name: 'statistics')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Statistics', $this->buildUserStatistics->exec($user));
    }
}
