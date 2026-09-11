<?php

namespace App\Http\Middleware;

use App\Actions\Seo\BuildPageMeta;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly BuildPageMeta $buildPageMeta,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'backgroundMap' => $this->randomMapUrl(),
            'meta' => $this->buildPageMeta->exec($request)->toArray(),
        ];
    }

    private function randomMapUrl(): string
    {
        $maps = glob(public_path('maps/*.png')) ?: [];

        if ($maps === []) {
            return '/maps/Hellfire_Peninsula.png';
        }

        return '/maps/'.basename($maps[array_rand($maps)]);
    }
}
