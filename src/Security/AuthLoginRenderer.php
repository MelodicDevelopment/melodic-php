<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthLoginRenderer
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly AuthProviderRegistry $registry,
    ) {
    }

    public function render(?string $error = null): string
    {
        $providers = $this->registry->all();
        $loginPath = $this->config->loginPath;
        $callbackPath = $this->config->callbackPath;

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 2rem; width: 100%; max-width: 400px; }
        .login-card h1 { font-size: 1.5rem; margin-bottom: 1.5rem; text-align: center; color: #333; }
        .error { background: #fee; border: 1px solid #fcc; color: #c33; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.875rem; }
        .provider-btn { display: block; width: 100%; padding: 0.75rem 1rem; margin-bottom: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #fff; color: #333; font-size: 1rem; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s; }
        .provider-btn:hover { background: #f0f0f0; }
        .divider { display: flex; align-items: center; margin: 1.25rem 0; color: #999; font-size: 0.875rem; }
        .divider::before, .divider::after { content: ""; flex: 1; border-bottom: 1px solid #ddd; }
        .divider::before { margin-right: 0.75rem; }
        .divider::after { margin-left: 0.75rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: #555; }
        .form-group input { width: 100%; padding: 0.625rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #4a90d9; box-shadow: 0 0 0 2px rgba(74,144,217,0.2); }
        .submit-btn { display: block; width: 100%; padding: 0.75rem; border: none; border-radius: 4px; background: #4a90d9; color: #fff; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        .submit-btn:hover { background: #357abd; }
    </style>
</head>
<body>
<div class="login-card">
    <h1>Sign In</h1>
HTML;

        if ($error !== null && $error !== '') {
            $escapedError = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
            $html .= "<div class=\"error\">{$escapedError}</div>";
        }

        $externalProviders = [];
        $localProvider = null;

        foreach ($providers as $provider) {
            if ($provider->getType() === AuthProviderType::Local) {
                $localProvider = $provider;
            } else {
                $externalProviders[] = $provider;
            }
        }

        foreach ($externalProviders as $provider) {
            $label = htmlspecialchars($provider->getLabel() ?: $provider->getName(), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars(rtrim($loginPath, '/') . '/' . $provider->getName(), ENT_QUOTES, 'UTF-8');
            $html .= "<a href=\"{$url}\" class=\"provider-btn\">{$label}</a>";
        }

        if ($localProvider !== null && count($externalProviders) > 0) {
            $html .= '<div class="divider">or</div>';
        }

        if ($localProvider !== null) {
            $formAction = htmlspecialchars(rtrim($callbackPath, '/') . '/' . $localProvider->getName(), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($localProvider->getLabel() ?: 'Sign in with Email', ENT_QUOTES, 'UTF-8');
            $html .= <<<HTML
    <form method="POST" action="{$formAction}">
        <div class="form-group">
            <label for="username">Email or Username</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="submit-btn">{$label}</button>
    </form>
HTML;
        }

        $html .= '</div></body></html>';

        return $html;
    }
}
