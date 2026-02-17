<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthLoginRenderer implements AuthLoginRendererInterface
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly AuthProviderRegistry $registry,
    ) {
    }

    public function render(?string $error = null, ?string $csrfToken = null): string
    {
        $providers = $this->registry->all();
        $loginPath = $this->config->loginPath;
        $callbackPath = $this->config->callbackPath;
        $page = $this->config->loginPage;

        $title = htmlspecialchars($page->title, ENT_QUOTES, 'UTF-8');
        $primaryColor = htmlspecialchars($page->primaryColor, ENT_QUOTES, 'UTF-8');
        $primaryHoverColor = htmlspecialchars($page->primaryHoverColor, ENT_QUOTES, 'UTF-8');
        $backgroundColor = htmlspecialchars($page->backgroundColor, ENT_QUOTES, 'UTF-8');
        $cardBackground = htmlspecialchars($page->cardBackground, ENT_QUOTES, 'UTF-8');
        $textColor = htmlspecialchars($page->textColor, ENT_QUOTES, 'UTF-8');
        $subtextColor = htmlspecialchars($page->subtextColor, ENT_QUOTES, 'UTF-8');

        $faviconTag = '';
        if ($page->faviconUrl !== null) {
            $faviconHref = htmlspecialchars($page->faviconUrl, ENT_QUOTES, 'UTF-8');
            $faviconTag = "<link rel=\"icon\" href=\"{$faviconHref}\">";
        }

        $customCssBlock = '';
        if ($page->customCss !== null) {
            $customCssBlock = "<style>{$page->customCss}</style>";
        }

        $logoHtml = '';
        if ($page->logoUrl !== null) {
            $logoSrc = htmlspecialchars($page->logoUrl, ENT_QUOTES, 'UTF-8');
            $logoAltText = htmlspecialchars($page->logoAlt ?? $page->title, ENT_QUOTES, 'UTF-8');
            $logoHtml = "<div class=\"logo\"><img src=\"{$logoSrc}\" alt=\"{$logoAltText}\"></div>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    {$faviconTag}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: {$backgroundColor}; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: {$cardBackground}; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 2rem; width: 100%; max-width: 400px; }
        .login-card h1 { font-size: 1.5rem; margin-bottom: 1.5rem; text-align: center; color: {$textColor}; }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        .logo img { max-width: 200px; max-height: 80px; }
        .error { background: #fee; border: 1px solid #fcc; color: #c33; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.875rem; }
        .provider-btn { display: block; width: 100%; padding: 0.75rem 1rem; margin-bottom: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: {$cardBackground}; color: {$textColor}; font-size: 1rem; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s; }
        .provider-btn:hover { background: #f0f0f0; }
        .divider { display: flex; align-items: center; margin: 1.25rem 0; color: #999; font-size: 0.875rem; }
        .divider::before, .divider::after { content: ""; flex: 1; border-bottom: 1px solid #ddd; }
        .divider::before { margin-right: 0.75rem; }
        .divider::after { margin-left: 0.75rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: {$subtextColor}; }
        .form-group input { width: 100%; padding: 0.625rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: {$primaryColor}; box-shadow: 0 0 0 2px {$primaryColor}33; }
        .submit-btn { display: block; width: 100%; padding: 0.75rem; border: none; border-radius: 4px; background: {$primaryColor}; color: #fff; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        .submit-btn:hover { background: {$primaryHoverColor}; }
    </style>
    {$customCssBlock}
</head>
<body>
<div class="login-card">
    {$logoHtml}
    <h1>{$title}</h1>
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
            $csrfField = $csrfToken !== null
                ? '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                : '';
            $html .= <<<HTML
    <form method="POST" action="{$formAction}">
        {$csrfField}
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
