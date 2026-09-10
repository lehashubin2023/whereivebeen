<?php

namespace App\Http\Controllers;

use App\Actions\Addon\ResolveAddonDownload;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;

#[Middleware(['auth', 'deny-admins'])]
class AddonController extends Controller
{
    #[Get('addon', name: 'addon')]
    public function index(ResolveAddonDownload $resolveAddonDownload): Response
    {
        return Inertia::render('Addon', [
            'addon' => $resolveAddonDownload->exec(),
        ]);
    }
}
