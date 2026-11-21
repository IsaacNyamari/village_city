<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps search engines out of everything that isn't the public website: the staff/customer app,
 * login and registration, Livewire endpoints and uploaded files. (Public pages set their own robots
 * meta from Administration → SEO & Site Content.)
 */
class SeoHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $name = (string) $request->route()?->getName();
        $public = $name === 'home' || str_starts_with($name, 'site.') || str_starts_with($name, 'seo.');

        if (! $public && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
