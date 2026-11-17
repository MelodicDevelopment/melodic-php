<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Exception\HttpException;
use Melodic\Http\Exception\MethodNotAllowedException;
use Melodic\Http\HttpMethod;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class AuthCallbackMiddleware implements MiddlewareInterface
{
    private const LOGIN_ERROR_KEY = 'melodic_login_error';

    private readonly CsrfToken $csrf;

    public function __construct(
        private readonly AuthConfig $config,
        private readonly AuthProviderRegistry $registry,
        private readonly SessionManager $session,
        private readonly AuthLoginRendererInterface $loginRenderer,
        private readonly ?RefreshTokenConfig $refreshTokenConfig = null,
    ) {
        $this->csrf = new CsrfToken($this->session);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->path();

        // POST /auth/logout — logout is a state-changing action, so it must be a
        // CSRF-protected POST rather than a GET that can be triggered cross-site.
        if ($path === '/auth/logout') {
            return $this->handleLogout($request);
        }

        // GET /auth/login — show login page with all providers
        // (with or without a trailing slash — "/auth/login/" is not a provider)
        if ($path === $this->config->loginPath || $path === rtrim($this->config->loginPath, '/') . '/') {
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
        // The message is read from the session, never from the URL: a
        // message taken from `?error=...` would let anyone craft a link that
        // shows arbitrary text on the real login page (phishing). The query
        // parameter is only a flag.
        $error = null;

        if ($request->query('error') !== null) {
            $stored = $this->session->get(self::LOGIN_ERROR_KEY);
            $this->session->remove(self::LOGIN_ERROR_KEY);
            $error = is_string($stored) && $stored !== ''
                ? $stored
                : 'Sign-in failed. Please try again.';
        }

        $csrfToken = $this->csrf->getToken();
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
                return $this->loginErrorRedirect('Invalid or expired form submission. Please try again.');
            }
        }

        try {
            $result = $provider->handleCallback($request, $this->session);
        } catch (SecurityException $e) {
            return $this->loginErrorRedirect($e->getMessage());
        }

        // Regenerate session ID after successful authentication to prevent session
        // fixation. Go through the session abstraction so non-native drivers are
        // covered too (a raw session_regenerate_id() would silently skip them).
        $this->session->regenerate(true);

        $redirectTo = (string) $this->session->get('melodic_redirect_after_login', $this->config->postLoginRedirect);
        $this->session->remove('melodic_redirect_after_login');

        // Defense-in-depth against open redirects: even if the stored value was
        // tampered with, only local paths are honored; anything else falls back
        // to the author-configured post-login target.
        if (!RedirectResponse::isLocalPath($redirectTo)) {
            $redirectTo = $this->config->postLoginRedirect;
        }

        $response = new RedirectResponse($redirectTo);

        return $response->withCookie($this->config->cookieName, $result->token, $this->cookieOptions(
            time() + $this->config->cookieLifetime,
        ));
    }

    private function handleLogout(Request $request): Response
    {
        // Only POST may log out, and only with a valid CSRF token.
        if ($request->method() !== HttpMethod::POST) {
            throw MethodNotAllowedException::forMethods(['POST']);
        }

        $submittedToken = (string) $request->body('csrf_token', '');

        if ($submittedToken === '' || !$this->csrf->validate($submittedToken)) {
            throw HttpException::forbidden('Invalid or expired logout request.');
        }

        // End the server-side session too, not just the auth cookie —
        // otherwise session data (and the session id) outlives the logout.
        $this->session->destroy();

        $response = new RedirectResponse('/');

        // Drop the refresh-token cookie as well when refresh tokens are in
        // use. (Revoking the stored token family is the application's job —
        // call RefreshTokenService::revokeAllForUser() from a logout hook.)
        if ($this->refreshTokenConfig !== null) {
            $response = RefreshTokenCookieHelper::clear($response, $this->refreshTokenConfig);
        }

        // Clear with the same attributes the cookie was set with — otherwise the
        // browser keeps the original cookie.
        return $response->withCookie($this->config->cookieName, '', $this->cookieOptions(time() - 3600));
    }

    /** Stash the message in the session and bounce back to the login page. */
    private function loginErrorRedirect(string $message): Response
    {
        $this->session->set(self::LOGIN_ERROR_KEY, $message);

        return new RedirectResponse("{$this->config->loginPath}?error=1");
    }

    /**
     * Cookie attributes for the auth cookie, sourced from config so they match
     * between set and clear (secure defaults on).
     *
     * @return array<string, mixed>
     */
    private function cookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => $this->config->cookiePath,
            'domain' => $this->config->cookieDomain,
            'secure' => $this->config->cookieSecure,
            'httponly' => true,
            'samesite' => $this->config->cookieSameSite,
        ];
    }
}
