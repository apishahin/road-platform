<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Page;
use App\Support\IssuePresenter;
use Illuminate\Support\Facades\Cache;

/**
 * Search-engine entry points: an XML sitemap (rebuilt at most every 6 hours)
 * and robots.txt pointing to it.
 */
class SitemapController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), fn () => $this->buildSitemap());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        return response(
            "User-agent: *\n"
            ."Allow: /\n"
            ."Disallow: /admin\n"
            ."Disallow: /login\n"
            ."Disallow: /photo-review\n"
            ."Disallow: /verify-queue\n"
            ."\nSitemap: ".url('/sitemap.xml')."\n",
            200, ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function buildSitemap(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $pri = '0.5') use (&$x): void {
            $x .= "  <url>\n    <loc>".e($loc)."</loc>\n";
            if ($lastmod) {
                $x .= '    <lastmod>'.\Carbon\Carbon::parse($lastmod)->toDateString()."</lastmod>\n";
            }
            $x .= "    <changefreq>{$freq}</changefreq>\n    <priority>{$pri}</priority>\n  </url>\n";
        };

        $add(url('/'), now(), 'daily', '1.0');
        $add(route('issues.index'), now(), 'daily', '0.9');
        $add(route('dashboard'), now(), 'hourly', '0.6');
        $add(route('fund.show'), now(), 'weekly', '0.6');

        foreach (Page::where('is_published', true)->get(['slug', 'updated_at']) as $p) {
            $add(route('page.show', $p->slug), $p->updated_at, 'monthly', '0.5');
        }

        Issue::whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(5000)
            ->get(['id', 'public_id', 'published_at', 'last_activity_at'])
            ->each(fn ($i) => $add(IssuePresenter::url($i), $i->last_activity_at ?? $i->published_at, 'daily', '0.8'));

        return $x."</urlset>\n";
    }
}
