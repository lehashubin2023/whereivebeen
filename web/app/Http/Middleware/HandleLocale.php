<?php

namespace App\Http\Middleware;

use App\Enums\LocaleEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = $user instanceof User
            ? $user->locale
            : $this->preferred($request);

        app()->setLocale($locale->value);

        View::share('locale', $locale->value);
        View::share('translations', $this->translations($locale));

        return $next($request);
    }

    private function preferred(Request $request): LocaleEnum
    {
        $supported = array_map(
            fn (LocaleEnum $locale): string => $locale->value,
            LocaleEnum::cases(),
        );

        $preferred = $request->getPreferredLanguage($supported);

        return LocaleEnum::tryFrom((string) $preferred)
            ?? LocaleEnum::tryFrom(config()->string('app.locale'))
            ?? LocaleEnum::EN;
    }

    /**
     * @return array<string, string>
     */
    private function translations(LocaleEnum $locale): array
    {
        $path = lang_path($locale->value.'.json');

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, string> $decoded */
        return $decoded;
    }
}
