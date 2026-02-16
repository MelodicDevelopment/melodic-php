<?php

declare(strict_types=1);

namespace Example\Security;

use Melodic\Security\AuthConfig;
use Melodic\Security\AuthLoginRendererInterface;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\AuthProviderType;

class ExampleLoginRenderer implements AuthLoginRendererInterface
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

		$externalProviders = [];
		$localProvider = null;

		foreach ($providers as $provider) {
			if ($provider->getType() === AuthProviderType::Local) {
				$localProvider = $provider;
			} else {
				$externalProviders[] = $provider;
			}
		}

		$errorHtml = '';
		if ($error !== null && $error !== '') {
			$escaped = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
			$errorHtml = <<<HTML
			<div class="error-banner">
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5v4M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
				<span>{$escaped}</span>
			</div>
HTML;
		}

		$externalHtml = '';
		foreach ($externalProviders as $provider) {
			$label = htmlspecialchars($provider->getLabel() ?: $provider->getName(), ENT_QUOTES, 'UTF-8');
			$url = htmlspecialchars(rtrim($loginPath, '/') . '/' . $provider->getName(), ENT_QUOTES, 'UTF-8');
			$name = strtolower($provider->getName());
			$icon = $this->providerIcon($name);
			$externalHtml .= <<<HTML
			<a href="{$url}" class="oauth-btn oauth-{$name}">
				{$icon}
				<span>{$label}</span>
			</a>
HTML;
		}

		$dividerHtml = '';
		if ($localProvider !== null && !empty($externalProviders)) {
			$dividerHtml = '<div class="divider"><span>or continue with email</span></div>';
		}

		$localFormHtml = '';
		if ($localProvider !== null) {
			$formAction = htmlspecialchars(rtrim($callbackPath, '/') . '/' . $localProvider->getName(), ENT_QUOTES, 'UTF-8');
			$btnLabel = htmlspecialchars($localProvider->getLabel() ?: 'Sign In', ENT_QUOTES, 'UTF-8');
			$localFormHtml = <<<HTML
			<form method="POST" action="{$formAction}" class="login-form">
				<div class="field">
					<label for="username">Email or Username</label>
					<input type="text" id="username" name="username" placeholder="you@example.com" required autocomplete="username">
				</div>
				<div class="field">
					<label for="password">Password</label>
					<input type="password" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
				</div>
				<button type="submit" class="submit-btn">{$btnLabel}</button>
			</form>
HTML;
		}

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sign In - Melodic Example</title>
	<style>
		:root {
			--surface: #ffffff;
			--background: #0f1117;
			--background-secondary: #161922;
			--accent: #6c63ff;
			--accent-hover: #5a52e0;
			--accent-glow: rgba(108, 99, 255, 0.25);
			--text: #e4e4e7;
			--text-muted: #8b8b9e;
			--border: #2a2d3a;
			--input-bg: #1c1f2e;
			--error: #f87171;
			--error-bg: rgba(248, 113, 113, 0.1);
			--error-border: rgba(248, 113, 113, 0.25);
			--radius: 10px;
		}

		* { margin: 0; padding: 0; box-sizing: border-box; }

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, sans-serif;
			background: var(--background);
			color: var(--text);
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 2rem;
		}

		body::before {
			content: '';
			position: fixed;
			top: -50%;
			left: -50%;
			width: 200%;
			height: 200%;
			background: radial-gradient(ellipse at 30% 20%, rgba(108, 99, 255, 0.08) 0%, transparent 50%),
						radial-gradient(ellipse at 70% 80%, rgba(99, 179, 255, 0.05) 0%, transparent 50%);
			z-index: -1;
		}

		.brand {
			text-align: center;
			margin-bottom: 2rem;
		}

		.brand-icon {
			display: inline-flex;
			align-items: center;
			margin-bottom: 1rem;
		}

		.brand-icon svg { width: 64px; height: auto; }

		.brand h1 {
			font-size: 1.5rem;
			font-weight: 600;
			letter-spacing: -0.025em;
			margin-bottom: 0.375rem;
		}

		.brand p {
			color: var(--text-muted);
			font-size: 0.9rem;
		}

		.card {
			background: var(--background-secondary);
			border: 1px solid var(--border);
			border-radius: 16px;
			padding: 2rem;
			width: 100%;
			max-width: 400px;
			box-shadow: 0 8px 40px rgba(0, 0, 0, 0.3);
		}

		.error-banner {
			display: flex;
			align-items: center;
			gap: 0.625rem;
			background: var(--error-bg);
			border: 1px solid var(--error-border);
			color: var(--error);
			padding: 0.75rem 1rem;
			border-radius: var(--radius);
			margin-bottom: 1.25rem;
			font-size: 0.85rem;
			line-height: 1.4;
		}

		.error-banner svg { flex-shrink: 0; }

		.oauth-btn {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.625rem;
			width: 100%;
			padding: 0.7rem 1rem;
			margin-bottom: 0.625rem;
			border: 1px solid var(--border);
			border-radius: var(--radius);
			background: var(--input-bg);
			color: var(--text);
			font-size: 0.9rem;
			font-weight: 500;
			cursor: pointer;
			text-decoration: none;
			transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
		}

		.oauth-btn:hover {
			border-color: #3d4155;
			background: #222538;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
		}

		.oauth-btn svg {
			width: 18px;
			height: 18px;
			flex-shrink: 0;
		}

		.divider {
			display: flex;
			align-items: center;
			margin: 1.5rem 0;
			color: var(--text-muted);
			font-size: 0.8rem;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		.divider::before, .divider::after {
			content: '';
			flex: 1;
			border-bottom: 1px solid var(--border);
		}

		.divider span { padding: 0 0.875rem; }

		.login-form .field { margin-bottom: 1rem; }

		.login-form label {
			display: block;
			margin-bottom: 0.375rem;
			font-size: 0.8rem;
			font-weight: 500;
			color: var(--text-muted);
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.login-form input {
			width: 100%;
			padding: 0.7rem 0.875rem;
			background: var(--input-bg);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			color: var(--text);
			font-size: 0.9rem;
			font-family: inherit;
			transition: border-color 0.2s, box-shadow 0.2s;
		}

		.login-form input::placeholder {
			color: #4a4d5e;
		}

		.login-form input:focus {
			outline: none;
			border-color: var(--accent);
			box-shadow: 0 0 0 3px var(--accent-glow);
		}

		.submit-btn {
			display: block;
			width: 100%;
			padding: 0.75rem;
			margin-top: 0.5rem;
			border: none;
			border-radius: var(--radius);
			background: linear-gradient(135deg, var(--accent), #5a52e0);
			color: #fff;
			font-size: 0.9rem;
			font-weight: 600;
			font-family: inherit;
			cursor: pointer;
			transition: opacity 0.2s, box-shadow 0.2s, transform 0.1s;
			box-shadow: 0 2px 12px var(--accent-glow);
		}

		.submit-btn:hover {
			opacity: 0.92;
			box-shadow: 0 4px 20px var(--accent-glow);
		}

		.submit-btn:active { transform: scale(0.98); }

		.footer {
			margin-top: 1.75rem;
			text-align: center;
			color: var(--text-muted);
			font-size: 0.8rem;
		}

		.footer a {
			color: var(--accent);
			text-decoration: none;
		}

		.footer a:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<div class="brand">
		<div class="brand-icon">
			<svg viewBox="0 0 3125 1875" fill-rule="evenodd">
				<path d="M983.632,984l-871.984,-872.312l0,1571.75l454.496,0l0,-493.663l417.488,417.923l0,-623.695Z" fill="#ff0082"/>
				<path d="M983.01,984l871.983,-872.312l0,1571.75l-454.496,0l0,-493.663l-417.487,417.923l0,-623.695Z" fill="#49216d"/>
				<path d="M2336.05,514.918l0,765.288c211.187,0 382.644,-171.457 382.644,-382.644c0,-211.187 -171.457,-382.644 -382.644,-382.644Z" fill="#009dd9"/>
			</svg>
		</div>
		<h1>Melodic Example</h1>
		<p>Sign in to your account</p>
	</div>

	<div class="card">
		{$errorHtml}
		{$externalHtml}
		{$dividerHtml}
		{$localFormHtml}
	</div>

	<div class="footer">
		Powered by <a href="#">Melodic PHP Framework</a>
	</div>
</body>
</html>
HTML;
	}

	private function providerIcon(string $name): string
	{
		return match ($name) {
			'google' => '<svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09a7.12 7.12 0 010-4.18V7.07H2.18A11.99 11.99 0 001 12c0 1.94.46 3.77 1.18 5.43l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>',
			'github' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>',
			'microsoft' => '<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="9.5" height="9.5" fill="#F25022"/><rect x="12.5" y="2" width="9.5" height="9.5" fill="#7FBA00"/><rect x="2" y="12.5" width="9.5" height="9.5" fill="#00A4EF"/><rect x="12.5" y="12.5" width="9.5" height="9.5" fill="#FFB900"/></svg>',
			default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
		};
	}
}
