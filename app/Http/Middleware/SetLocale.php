<?php

namespace Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SetLocale.
 *
 * Sets the locale of a request (alias `locale`), so its messages, such as
 * validation errors, are translated: the signed-in user's locale, the best
 * match of `Accept-Language`, or else the app's default (`app.locale`), of
 * the locales, which `api-core.locales` supports. The response names it in
 * `Content-Language`. It runs after authentication, so it knows the user,
 * however a route orders them.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class SetLocale
{
    /**
     * Alias of the middleware.
     *
     * @var string
     */
    public const string ALIAS = 'locale';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->localeOf($request);

        App::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', str_replace('_', '-', $locale));

        return $response;
    }

    /**
     * Get the locale of the request: the user's one, when it is supported,
     * the best match of `Accept-Language`, or else the app's default.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function localeOf(Request $request): string
    {
        $default = (string) config('app.locale');
        $supported = config('api-core.locales', []);
        $userLocale = data_get($request->user(), 'locale');

        if (is_string($userLocale) && in_array($userLocale, $supported, true)) {
            return $userLocale;
        }

        if (empty($request->headers->get('Accept-Language'))) {
            return $default;
        }

        // A header, which matches none of them, gets the first one: the default
        return (string) $request->getPreferredLanguage(array_values(array_unique([$default, ...$supported])));
    }
}
