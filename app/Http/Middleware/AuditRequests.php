<?php

namespace Core\Http\Middleware;

use Closure;
use Core\Models\AuditLog;
use Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Class AuditRequests.
 *
 * Records the requests of its routes in the audit log (alias `audit`), after
 * their responses are sent: their method, route, status and user, the user,
 * who switched to the account, if any, and what its scopes ask for (see
 * `api-core.audit.scopes`, or the parameters, e.g. `audit:http,data`).
 * Successful GET requests are recorded only with the `reads` scope, while
 * errors are always recorded, with the response and the exception, whose
 * real message a production response hides. A request, which an earlier
 * middleware stops, such as an unauthenticated one, is recorded too.
 * Recording is best-effort: a failure is reported, never thrown.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuditRequests
{
    /**
     * Alias of the middleware.
     *
     * @var string
     */
    public const string ALIAS = 'audit';

    /**
     * Attribute of the request, which keeps the scopes of its audit.
     *
     * @var string
     */
    public const string SCOPES_ATTRIBUTE = 'api-core.audit_scopes';

    /**
     * Value, which a scrubbed field's value is recorded as.
     *
     * @var string
     */
    public const string MASK = '********';

    /**
     * Handle an incoming request, keeping the scopes of its audit.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$scopes
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $request->attributes->set(static::SCOPES_ATTRIBUTE, empty($scopes) ? $this->defaultScopes() : $scopes);

        return $next($request);
    }

    /**
     * Record the request in the audit log, after its response is sent.
     *
     * @param Request $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Record the request in the audit log, unless it is excluded.
     *
     * @param Request $request
     * @param Response $response
     *
     * @return void
     */
    protected function record(Request $request, Response $response): void
    {
        $scopes = $this->scopesOf($request);
        $isError = $response->isClientError() || $response->isServerError();

        if ($this->isExcluded($request) || ($request->isMethod('GET') && !$isError && !in_array('reads', $scopes))) {
            return;
        }

        $user = $request->user();
        $impersonator = $user instanceof User ? $user->currentImpersonator() : null;
        $metadata = [];

        if ($impersonator !== null) {
            $metadata['impersonator_id'] = $impersonator->getKey();
        }

        if (in_array('http', $scopes)) {
            $metadata['http'] = $this->httpOf($request, $response);
        }

        if (in_array('data', $scopes)) {
            $metadata['payload'] = $this->payloadOf($request);
        }

        if ($isError) {
            $metadata['error'] = $this->errorOf($response);
        }

        AuditLog::record(strtolower($request->method()), null, [
            'causer' => $user,
            'source' => AuditLog::SOURCE_REQUEST,
            'name' => $this->nameOf($request),
            'status_code' => $response->getStatusCode(),
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    /**
     * Determine if the request isn't recorded: one, which only asks about
     * the API (HEAD, OPTIONS), or one, which the config excludes.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function isExcluded(Request $request): bool
    {
        return $request->isMethod('HEAD')
            || $request->isMethod('OPTIONS')
            || $request->is(...config('api-core.audit.except.paths', []))
            || $request->routeIs(...config('api-core.audit.except.names', []));
    }

    /**
     * Get the scopes of the request's audit: the ones, which the middleware
     * kept, or, when an earlier middleware stopped the request, the ones,
     * which its route gives the middleware.
     *
     * @param Request $request
     *
     * @return array<int, string>
     */
    protected function scopesOf(Request $request): array
    {
        if ($request->attributes->has(static::SCOPES_ATTRIBUTE)) {
            return $request->attributes->get(static::SCOPES_ATTRIBUTE);
        }

        $route = $this->routeOf($request);

        if ($route !== null) {
            foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
                if (is_string($middleware) && Str::before($middleware, ':') === static::class) {
                    $parameters = Str::after($middleware, static::class . ':');

                    return $parameters === $middleware ? $this->defaultScopes() : explode(',', $parameters);
                }
            }
        }

        return $this->defaultScopes();
    }

    /**
     * Get the route of the request, unless it wasn't routed, such as a request,
     * which no route matched (`Request::route()` claims to always give one).
     *
     * @param Request $request
     *
     * @return Route|null
     */
    protected function routeOf(Request $request): ?Route
    {
        $route = call_user_func($request->getRouteResolver());

        return $route instanceof Route ? $route : null;
    }

    /**
     * Get the scopes of an audit, which its middleware gives no parameters.
     *
     * @return array<int, string>
     */
    protected function defaultScopes(): array
    {
        return config('api-core.audit.scopes', []);
    }

    /**
     * Get the name of the request's entry: its route's name or URI, or else
     * its path.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function nameOf(Request $request): string
    {
        $route = $this->routeOf($request);

        if ($route === null) {
            return $request->path();
        }

        return $route->getName() ?? $route->uri();
    }

    /**
     * Describe the HTTP side of the request and its response.
     *
     * @param Request $request
     * @param Response $response
     *
     * @return array<string, mixed>
     */
    protected function httpOf(Request $request, Response $response): array
    {
        $parameters = $this->routeOf($request)?->parameters() ?? [];

        return [
            'method' => $request->method(),
            'url' => $request->getRequestUri(),
            'parameters' => array_map(
                fn (mixed $value) => $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null),
                $parameters
            ),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'duration_ms' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : null,
        ];
    }

    /**
     * Get the request's payload with its scrub fields masked, unless it is
     * too large.
     *
     * @param Request $request
     *
     * @return array<array-key, mixed>|string
     */
    protected function payloadOf(Request $request): array|string
    {
        $size = (int) ($request->header('Content-Length') ?? strlen((string) $request->getContent()));

        if ($size > config('api-core.audit.max_payload_size')) {
            return '[too large]';
        }

        return $this->scrub($request->all());
    }

    /**
     * Mask the values of the scrub fields at any depth, and describe the
     * uploaded files.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function scrub(array $data): array
    {
        $fields = array_map('strtolower', config('api-core.audit.scrub_fields', []));

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $fields, true)) {
                $data[$key] = static::MASK;
            } elseif ($value instanceof UploadedFile) {
                $data[$key] = sprintf('[file: %s, %d bytes]', $value->getClientOriginalName(), $value->getSize());
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }

    /**
     * Describe the error of the response: its contents, cut to the maximum
     * size, and the exception behind it.
     *
     * @param Response $response
     *
     * @return array<string, mixed>
     */
    protected function errorOf(Response $response): array
    {
        $error = [
            'response' => Str::limit((string) $response->getContent(), config('api-core.audit.max_payload_size'), '…'),
        ];

        $exception = property_exists($response, 'exception') ? $response->exception : null;

        if ($exception instanceof Throwable) {
            $error['exception'] = $this->describeException($exception);

            // Only server errors need the place, which they come from
            if ($response->isServerError()) {
                $error['exception']['trace'] = $this->traceOf($exception);
            }

            for ($previous = $exception->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
                $error['exception']['previous'][] = $this->describeException($previous);
            }
        }

        return $error;
    }

    /**
     * Describe the exception without its trace.
     *
     * @param Throwable $exception
     *
     * @return array{class: string, message: string, code: int|string, file: string, line: int}
     */
    protected function describeException(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    /**
     * Format the exception's trace as `getTraceAsString()` does, but without
     * the arguments of the calls, which may hold credentials or personal data.
     *
     * @param Throwable $exception
     *
     * @return string
     */
    protected function traceOf(Throwable $exception): string
    {
        $lines = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $lines[] = sprintf(
                '#%d %s: %s%s%s()',
                $index,
                isset($frame['file']) ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')' : '[internal function]',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function']
            );
        }

        $lines[] = sprintf('#%d {main}', count($lines));

        return implode("\n", $lines);
    }
}
