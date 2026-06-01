<?php

declare(strict_types=1);

/**
 * Minimal Melodic app for a live OIDC end-to-end test against Microsoft Entra.
 *
 * This is NOT a PHPUnit test (it is excluded from the test suite by filename) —
 * it is a tiny real app you run manually to click through the full OAuth/OIDC
 * login flow against a real identity provider. See ../README.md.
 *
 * Flow exercised:
 *   GET  /                       -> protected; redirects to /auth/login if no cookie
 *   GET  /auth/login            -> rendered login page with the Entra button
 *   GET  /auth/login/entra      -> PKCE + state, redirect to Entra
 *   GET  /auth/callback/entra   -> state/PKCE check, code exchange, token validation
 *   POST /auth/logout           -> CSRF-protected logout
 */

use Melodic\Controller\Controller;
use Melodic\Core\Application;
use Melodic\Http\Response;
use Melodic\Routing\Router;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\CsrfToken;
use Melodic\Security\SecurityServiceProvider;
use Melodic\Security\SessionManager;
use Melodic\Security\UserContext;
use Melodic\Security\WebAuthenticationMiddleware;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * Home page: shows the authenticated user's claims and a logout button. Reaching
 * it at all proves the cookie set by the callback validates on a fresh request
 * (signature + iss + aud + exp re-checked by WebAuthenticationMiddleware).
 */
class HomeController extends Controller
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function home(): Response
    {
        /** @var UserContext|null $ctx */
        $ctx = $this->request->getAttribute('userContext');
        $claims = $ctx?->getClaims() ?? [];

        $csrf = (new CsrfToken($this->session))->getToken();
        $csrfField = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($claims as $key => $value) {
            $k = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
            $v = htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td><code>{$k}</code></td><td><code>{$v}</code></td></tr>";
        }

        $username = htmlspecialchars((string) ($ctx?->getUsername() ?? 'unknown'), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Entra live test — signed in</title>
<style>
 body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:760px;margin:3rem auto;padding:0 1rem;color:#222}
 h1{font-size:1.4rem} table{border-collapse:collapse;width:100%;margin:1rem 0}
 td{border:1px solid #ddd;padding:.4rem .6rem;vertical-align:top;font-size:.85rem;word-break:break-all}
 td:first-child{white-space:nowrap;background:#f7f7f7;font-weight:600}
 .ok{background:#e8f5e9;border:1px solid #c8e6c9;color:#256029;padding:.6rem .8rem;border-radius:6px}
 button{padding:.5rem 1rem;border:0;border-radius:6px;background:#c62828;color:#fff;cursor:pointer}
</style></head>
<body>
 <div class="ok">✅ Signed in as <strong>{$username}</strong>. The Entra token validated end to end.</div>
 <h1>ID token claims</h1>
 <table>{$rows}</table>
 <form method="POST" action="/auth/logout">
   <input type="hidden" name="csrf_token" value="{$csrfField}">
   <button type="submit">Log out</button>
 </form>
</body></html>
HTML;

        return new Response(
            statusCode: 200,
            body: $html,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}

$app = new Application(dirname(__DIR__));
$app->loadEnvironmentConfig();          // config/config.json → config/config.dev.json (secrets)
$app->register(new SecurityServiceProvider());

// AuthCallbackMiddleware intercepts /auth/* (login page, provider redirect, callback,
// logout). WebAuthenticationMiddleware protects everything else and, on a valid
// cookie, attaches the UserContext for the controller to read. Order matters:
// the auth routes must be handled before the "protect everything" middleware.
$app->addMiddleware($app->getContainer()->get(AuthCallbackMiddleware::class));
$app->addMiddleware($app->getContainer()->get(WebAuthenticationMiddleware::class));

$app->routes(function (Router $router): void {
    $router->get('/', HomeController::class, 'home');
});

$app->run();
