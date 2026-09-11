<?php

namespace App\Http\Controllers;

use App\Actions\Support\ResolveSupportChannels;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;

class SupportController extends Controller
{
    #[Get('support', name: 'support')]
    public function index(ResolveSupportChannels $resolveSupportChannels): Response
    {
        return Inertia::render('Support', [
            'channels' => $resolveSupportChannels->exec(),
        ]);
    }
}
