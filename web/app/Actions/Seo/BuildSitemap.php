<?php

namespace App\Actions\Seo;

use App\Actions\Support\ResolveSupportChannels;

class BuildSitemap
{
    public function __construct(private readonly ResolveSupportChannels $supportChannels) {}

    public function exec(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($this->urls() as $url) {
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.$this->escape($url).'</loc>';
            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<int, string>
     */
    public function urls(): array
    {
        return array_map(fn (string $route): string => route($route), $this->routes());
    }

    /**
     * @return array<int, string>
     */
    private function routes(): array
    {
        $hidden = $this->supportChannels->available() ? [] : ['support'];

        return array_values(array_filter(
            config()->array('seo.indexable'),
            fn (mixed $name): bool => is_string($name) && ! in_array($name, $hidden, true),
        ));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
