<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;

#[Middleware('auth')]
class DashboardController extends Controller
{
    #[Get('dashboard', name: 'dashboard')]
    public function index(): Response
    {
        return Inertia::render('Dashboard');
    }
}
