<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class OAuthCallbackMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly OAuthClient $oauthClient,
        private readonly JwtValidator $validator,
        private readonly SessionManager $session,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->path();

        if ($path === $this->config->loginPath) {
            return $this->handleLogin();
        }

        if ($path === $this->config->callbackPath) {
            return $this->handleCallback($request);
        }

        return $handler->handle($request);
    }

    private function handleLogin(): Response
    {
        $state = OAuthClient::generateState();
        $codeVerifier = OAuthClient::generateCodeVerifier();

        $this->session->set('melodic_oauth_state', $state);
        $this->session->set('melodic_oauth_code_verifier', $codeVerifier);

        $authorizationUrl = $this->oauthClient->getAuthorizationUrl($state, $codeVerifier);

        return new RedirectResponse($authorizationUrl);
    }

    private function handleCallback(Request $request): Response
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error !== null) {
            $description = $request->query('error_description', $error);
            throw new SecurityException('OAuth error: ' . $description);
        }

        if ($code === null || $state === null) {
            throw new SecurityException('Missing authorization code or state parameter.');
        }

        $savedState = $this->session->get('melodic_oauth_state');
        $codeVerifier = $this->session->get('melodic_oauth_code_verifier');

        $this->session->remove('melodic_oauth_state');
        $this->session->remove('melodic_oauth_code_verifier');

        if ($savedState === null || !hash_equals((string) $savedState, (string) $state)) {
            throw new SecurityException('Invalid OAuth state parameter.');
        }

        if ($codeVerifier === null) {
            throw new SecurityException('Missing PKCE code verifier.');
        }

        $tokenResponse = $this->oauthClient->exchangeCode((string) $code, (string) $codeVerifier);

        $token = $tokenResponse['access_token'] ?? $tokenResponse['id_token'] ?? null;

        if ($token === null) {
            throw new SecurityException('No token received from authorization server.');
        }

        // Validate the token to ensure it's legitimate
        $this->validator->validate($token);

        $redirectTo = $this->session->get('melodic_redirect_after_login', $this->config->postLoginRedirect);
        $this->session->remove('melodic_redirect_after_login');

        $response = new RedirectResponse((string) $redirectTo);

        return $response->withCookie($this->config->cookieName, $token, [
            'expires' => time() + $this->config->cookieLifetime,
            'path' => '/',
            'secure' => str_starts_with($this->config->redirectUri, 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
