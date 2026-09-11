<?php

namespace App\Http\Controllers;

use App\Actions\Addon\ResolveAddonDownload;
use App\Actions\Faq\ResolveFaqItems;
use App\Actions\Support\ResolveSupportChannels;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;

#[Group(prefix: '{locale}', where: ['locale' => 'en|ru'])]
class PageController extends Controller
{
    #[Get('', name: 'home')]
    public function home(ResolveAddonDownload $resolveAddonDownload): Response|RedirectResponse
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

    #[Get('faq', name: 'faq')]
    public function faq(ResolveFaqItems $resolveFaqItems): Response
    {
        return Inertia::render('Faq', [
            'heading' => trans('faq.heading'),
            'intro' => trans('faq.intro'),
            'items' => $resolveFaqItems->exec(),
        ]);
    }

    #[Get('support', name: 'support')]
    public function support(ResolveSupportChannels $resolveSupportChannels): Response
    {
        return Inertia::render('Support', [
            'channels' => $resolveSupportChannels->exec(),
        ]);
    }
}
