<?php

namespace Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ForceJsonOnApi.
 *
 * Makes requests to the API paths (`api-core.http.json_paths`) expect JSON,
 * as if they had `Accept: application/json`, so their responses, errors
 * included, are JSON. Otherwise, an unauthenticated request is redirected
 * to a login page, and other errors are rendered as HTML.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class ForceJsonOnApi
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isApiRequest($request)) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }

    /**
     * Determine if the request is made to one of the API paths.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function isApiRequest(Request $request): bool
    {
        $paths = config('api-core.http.json_paths', []);

        return $paths !== [] && $request->is(...$paths);
    }
}
