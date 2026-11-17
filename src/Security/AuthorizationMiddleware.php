<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class AuthorizationMiddleware implements MiddlewareInterface
{
    /** @param string[] $requiredEntitlements */
    public function __construct(
        private readonly array $requiredEntitlements = [],
        private readonly bool $requireAuthentication = true,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        /** @var UserContextInterface|null $userContext */
        $userContext = $request->getAttribute('userContext');

        if ($this->requireAuthentication && ($userContext === null || !$userContext->isAuthenticated())) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        // Entitlement requirements are enforced regardless of the
        // requireAuthentication flag: an anonymous request can never satisfy
        // an entitlement, so letting it pass here would silently disable the
        // check whenever requireAuthentication was turned off.
        $requiredEntitlements = $this->requiredEntitlements($request);

        if ($requiredEntitlements !== []) {
            if ($userContext === null || !$userContext->isAuthenticated()) {
                return new JsonResponse(['error' => 'Authentication required.'], 401);
            }

            if (!$userContext->hasAnyEntitlement(...$requiredEntitlements)) {
                return new JsonResponse(['error' => 'Insufficient permissions.'], 403);
            }
        }

        return $handler->handle($request);
    }

    /**
     * Constructor entitlements plus any declared on the matched route. Route
     * middleware is resolved from the container by class name, so it cannot
     * take constructor arguments — declare requirements as a route attribute:
     *
     *     $router->get('/admin', AdminController::class, 'index',
     *         middleware: [AuthorizationMiddleware::class],
     *         attributes: ['entitlements' => ['admin']]);
     *
     * The user needs at least one of the listed entitlements.
     *
     * @return string[]
     */
    private function requiredEntitlements(Request $request): array
    {
        $attributes = $request->getAttribute('route.attributes');
        $routeEntitlements = is_array($attributes) ? ($attributes['entitlements'] ?? []) : [];

        $all = array_merge($this->requiredEntitlements, (array) $routeEntitlements);

        return array_values(array_unique(array_filter($all, 'is_string')));
    }
}
