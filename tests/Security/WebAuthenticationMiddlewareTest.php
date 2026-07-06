<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\AuthConfig;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\JwtValidator;
use Melodic\Security\SessionManager;
use Melodic\Security\WebAuthenticationMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class WebAuthStubHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new Response(200, 'ok');
    }
}

class WebAuthenticationMiddlewareTest extends TestCase
{
    private function process(string $uri, SessionManager $session): Response
    {
        $middleware = new WebAuthenticationMiddleware(
            new AuthConfig(),
            new JwtValidator(new AuthProviderRegistry()),
            $session,
        );

        $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]);

        return $middleware->process($request, new WebAuthStubHandler());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStoresLocalPathAsPostLoginTarget(): void
    {
        $session = new SessionManager(cookieSecure: false);

        $response = $this->process('/reports/annual', $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/reports/annual', $session->get('melodic_redirect_after_login'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDoesNotStoreOffSitePathAsPostLoginTarget(): void
    {
        $session = new SessionManager(cookieSecure: false);

        // parse_url() keeps the backslash, and browsers turn "/\evil.com" in a
        // Location header into "//evil.com" — an off-site redirect.
        $response = $this->process('/\\evil.com', $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($session->has('melodic_redirect_after_login'));
    }
}
