<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleLocale;
use App\Http\Requests\Locale\LocaleUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Spatie\RouteAttributes\Attributes\Patch;

class LocaleController extends Controller
{
    private const COOKIE_LIFETIME = 60 * 24 * 365;

    #[Patch('locale', name: 'locale.update')]
    public function update(LocaleUpdateRequest $request): RedirectResponse
    {
        $locale = $request->locale();
        $user = $request->user();

        if ($user instanceof User) {
            $user->update(['locale' => $locale]);
        }

        Cookie::queue(HandleLocale::COOKIE, $locale->value, self::COOKIE_LIFETIME);

        return back();
    }
}
