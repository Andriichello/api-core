<?php

namespace Core\Http\Middleware;

use Closure;
use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ResolveOrganization.
 *
 * Sets CurrentOrganization for the request: the organization of the signed-in
 * user, or, for a super admin, the one in the `X-Organization-Id` header or,
 * for links, which can't send headers (downloads), the `x_organization_id`
 * query parameter. It
 * runs right after authentication (CoreServiceProvider ranks it so), before
 * route model bindings, which find models of the organization. An application
 * puts it, by its alias, after `auth:sanctum` on its routes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class ResolveOrganization
{
    /**
     * Alias of the middleware, which CoreServiceProvider registers.
     *
     * @var string
     */
    public const string ALIAS = 'organization';

    /**
     * Header, with which a super admin acts in another organization.
     *
     * @var string
     */
    public const string HEADER = 'X-Organization-Id';

    /**
     * Query parameter, with which a super admin acts in another organization,
     * when the request can't send the header.
     *
     * @var string
     */
    public const string QUERY = 'x_organization_id';

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
        $user = $request->user();

        CurrentOrganization::set($this->resolve($request, $user instanceof User ? $user : null));

        return $next($request);
    }

    /**
     * Get id of the organization, in which the request acts: the user's one,
     * or the one in the header or the query, which only a super admin may
     * choose.
     *
     * @param Request $request
     * @param User|null $user
     *
     * @return int|null
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    protected function resolve(Request $request, ?User $user): ?int
    {
        $id = $this->requestedId($request);

        if ($id === null) {
            return $user?->organization_id;
        }

        if ($id === $user?->organization_id) {
            return $id;
        }

        if (!$user?->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admins may act in another organization.');
        }

        if (!Organization::query()->whereKey($id)->exists()) {
            throw new NotFoundHttpException('The organization is not found.');
        }

        return $id;
    }

    /**
     * Get id of the organization, which the request names in the header or
     * the query, or null, when it names none.
     *
     * @param Request $request
     *
     * @return int|null
     * @throws BadRequestHttpException
     */
    protected function requestedId(Request $request): ?int
    {
        $header = (string) $request->header(static::HEADER);
        $query = $request->query(static::QUERY);

        if (!is_string($query ?? '')) {
            throw new BadRequestHttpException(static::QUERY . ' must be an id of an organization.');
        }

        $query = (string) $query;

        if ($header !== '' && $query !== '' && $header !== $query) {
            throw new BadRequestHttpException(static::HEADER . ' and ' . static::QUERY . ' must match.');
        }

        $value = $header !== '' ? $header : $query;

        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new BadRequestHttpException('The organization must be named by its id.');
        }

        return (int) $value;
    }
}
