<?php

namespace App\Http\Controllers;

use App\Actions\Seo\BuildRobotsTxt;
use App\Actions\Seo\BuildSitemap;
use Illuminate\Http\Response;
use Spatie\RouteAttributes\Attributes\Get;

class SeoController extends Controller
{
    #[Get('robots.txt', name: 'robots')]
    public function robots(BuildRobotsTxt $buildRobotsTxt): Response
    {
        return response($buildRobotsTxt->exec())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Get('sitemap.xml', name: 'sitemap')]
    public function sitemap(BuildSitemap $buildSitemap): Response
    {
        return response($buildSitemap->exec())
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
