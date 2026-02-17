<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\HttpMethod;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class AuthCallbackMiddleware implements MiddlewareInterface
{
    private readonly CsrfToken $csrf;

    public function __construct(
        private readonly AuthConfig $config,
        private readonly AuthProviderRegistry $registry,
        private readonly SessionManager $session,
        private readonly AuthLoginRendererInterface $loginRenderer,
    ) {
        $this->csrf = new CsrfToken($this->session);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->path();

        // GET /auth/logout
        if ($path === '/auth/logout') {
            return $this->handleLogout();
        }

        // GET /auth/login — show login page with all providers
        if ($path === $this->config->loginPath) {
            return $this->handleLoginPage($request);
        }

        // GET /auth/login/{provider} — initiate OAuth redirect
        $loginPrefix = rtrim($this->config->loginPath, '/') . '/';
        if (str_starts_with($path, $loginPrefix)) {
            $providerName = substr($path, strlen($loginPrefix));
            return $this->handleProviderLogin($request, $providerName);
        }

        // GET|POST /auth/callback/{provider} — handle OAuth callback or local form POST
        $callbackPrefix = rtrim($this->config->callbackPath, '/') . '/';
        if (str_starts_with($path, $callbackPrefix)) {
            $providerName = substr($path, strlen($callbackPrefix));
            return $this->handleProviderCallback($request, $providerName);
        }

        return $handler->handle($request);
    }

    private function handleLoginPage(Request $request): Response
    {
        $error = $request->query('error');
        $csrfToken = $this->csrf->generate();
        $html = $this->loginRenderer->render($error, $csrfToken);

        return new Response(
            statusCode: 200,
            body: $html,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function handleProviderLogin(Request $request, string $providerName): Response
    {
        if (!$this->registry->has($providerName)) {
            throw new SecurityException("Unknown auth provider: {$providerName}");
        }

        $provider = $this->registry->get($providerName);

        return $provider->handleLogin($request, $this->session);
    }

    private function handleProviderCallback(Request $request, string $providerName): Response
    {
        if (!$this->registry->has($providerName)) {
            throw new SecurityException("Unknown auth provider: {$providerName}");
        }

        $provider = $this->registry->get($providerName);

        // Validate CSRF token on POST requests to local auth providers
        if ($request->method() === HttpMethod::POST && $provider->getType() === AuthProviderType::Local) {
            $submittedToken = (string) $request->body('csrf_token', '');

            if ($submittedToken === '' || !$this->csrf->validate($submittedToken)) {
                $errorMessage = urlencode('Invalid or expired form submission. Please try again.');
                return new RedirectResponse("{$this->config->loginPath}?error={$errorMessage}");
            }
        }

        try {
            $result = $provider->handleCallback($request, $this->session);
        } catch (SecurityException $e) {
            $errorMessage = urlencode($e->getMessage());
            return new RedirectResponse("{$this->config->loginPath}?error={$errorMessage}");
        }

        // Regenerate session ID after successful authentication to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $redirectTo = $this->session->get('melodic_redirect_after_login', $this->config->postLoginRedirect);
        $this->session->remove('melodic_redirect_after_login');

        $response = new RedirectResponse((string) $redirectTo);

        return $response->withCookie($this->config->cookieName, $result->token, [
            'expires' => time() + $this->config->cookieLifetime,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function handleLogout(): Response
    {
        $response = new RedirectResponse('/');

        return $response->withCookie($this->config->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
