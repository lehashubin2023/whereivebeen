<?php

namespace App\Actions\Seo;

class BuildRobotsTxt
{
    private const DISALLOWED = [
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/profile',
        '/admin',
        '/addon',
        '/statistics',
        '/game-session',
        '/horizon',
    ];

    public function exec(): string
    {
        if (! app()->isProduction()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = ['User-agent: *', 'Allow: /'];

        foreach (self::DISALLOWED as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return implode("\n", $lines)."\n";
    }
}
