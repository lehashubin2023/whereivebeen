<?php

namespace App\Http\Controllers;

use App\Actions\Addon\ResolveAddonDownload;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;

class HomeController extends Controller
{
    #[Get('', name: 'home')]
    public function index(ResolveAddonDownload $resolveAddonDownload): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user->isAdmin()
                ? to_route('admin.issue-reports.index')
                : to_route('game-session.sessions');
        }

        return Inertia::render('Welcome', [
            'addon' => $resolveAddonDownload->exec(),
        ]);
    }
}
