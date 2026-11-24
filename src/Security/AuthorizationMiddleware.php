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
     * The user needs at least one of the listed entitlements. Entries are
     * strings or backed enums (their value is used); anything else throws,
     * because silently dropping an entry would leave the route unprotected.
     *
     * @return string[]
     */
    private function requiredEntitlements(Request $request): array
    {
        $attributes = $request->getAttribute('route.attributes');
        $routeEntitlements = is_array($attributes) ? ($attributes['entitlements'] ?? []) : [];

        if (!is_array($routeEntitlements)) {
            $routeEntitlements = [$routeEntitlements];
        }

        $required = [];

        foreach ([...$this->requiredEntitlements, ...array_values($routeEntitlements)] as $entitlement) {
            if ($entitlement instanceof \BackedEnum) {
                $entitlement = (string) $entitlement->value;
            }

            if (!is_string($entitlement) || $entitlement === '') {
                throw new \InvalidArgumentException(
                    'Route entitlements must be non-empty strings or backed enums, got ' . get_debug_type($entitlement) . '.'
                );
            }

            $required[] = $entitlement;
        }

        return array_values(array_unique($required));
    }
}
