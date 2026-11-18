<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a privacy-friendly page view AFTER the response is built.
 * Wrapped in try/catch — analytics must never break a real page.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->track($request, $response)) {
                DB::table('page_views')->insert([
                    'date'       => now()->toDateString(),
                    'path'       => Str::limit('/'.ltrim($request->path(), '/'), 190, ''),
                    'referrer'   => $this->referrerHost($request),
                    'vhash'      => $this->visitorHash($request),
                    'created_at' => now(),
                ]);

                // Auto-prune old rows once a day (180-day retention).
                if (Cache::add('analytics.pruned.'.now()->toDateString(), 1, 86400)) {
                    DB::table('page_views')->where('date', '<', now()->subDays(180)->toDateString())->delete();
                }
            }
        } catch (\Throwable) {
            // never break the site because of stats
        }

        return $response;
    }

    private function track(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }
        if ($request->is('admin*', 'login*', 'logout', 'up', 'sitemap.xml', 'robots.txt', 'manifest.json', 'fund/pay/*', 'api/*')) {
            return false;
        }

        $ua = strtolower((string) $request->userAgent());

        return ! preg_match('/bot|crawl|spider|slurp|facebookexternal|whatsapp|telegram|preview|curl|wget|python|pingdom|uptimerobot|monitor/i', $ua);
    }

    /** Referring site only (host, not the full URL); own site counts as direct. */
    private function referrerHost(Request $request): ?string
    {
        $ref = $request->headers->get('referer');
        if (! $ref) {
            return null;
        }
        $host = parse_url($ref, PHP_URL_HOST);
        if (! $host || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return Str::limit(strtolower($host), 190, '');
    }

    /** Anonymous, irreversible, and useless tomorrow — rotates every day. */
    private function visitorHash(Request $request): string
    {
        return substr(hash_hmac(
            'sha256',
            $request->ip().'|'.$request->userAgent().'|'.now()->toDateString(),
            (string) config('app.key')
        ), 0, 16);
    }
}
