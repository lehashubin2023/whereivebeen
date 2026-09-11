<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Spatie\RouteAttributes\Attributes\Get;

class LocaleRedirectController extends Controller
{
    #[Get('', name: 'root')]
    public function root(): RedirectResponse
    {
        return $this->to('home');
    }

    #[Get('support', name: 'support.redirect')]
    public function support(): RedirectResponse
    {
        return $this->to('support');
    }

    #[Get('faq', name: 'faq.redirect')]
    public function faq(): RedirectResponse
    {
        return $this->to('faq');
    }

    private function to(string $route): RedirectResponse
    {
        return redirect()->route($route, ['locale' => app()->getLocale()]);
    }
}
