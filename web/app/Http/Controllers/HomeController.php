<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;

#[Middleware('auth')]
class HomeController extends Controller
{
    #[Get('', name: 'home')]
    public function index(): RedirectResponse
    {
        return auth()->check() ? to_route('game-session.sessions') : to_route('login');
    }
}
