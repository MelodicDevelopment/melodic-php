<?php

declare(strict_types=1);

namespace Melodic\Security;

class LoginPageConfig
{
	public function __construct(
		public readonly string $title = 'Sign In',
		public readonly string $primaryColor = '#4a90d9',
		public readonly string $primaryHoverColor = '#357abd',
		public readonly string $backgroundColor = '#f5f5f5',
		public readonly string $cardBackground = '#ffffff',
		public readonly string $textColor = '#333333',
		public readonly string $subtextColor = '#555555',
		public readonly ?string $logoUrl = null,
		public readonly ?string $logoAlt = null,
		public readonly ?string $faviconUrl = null,
		public readonly ?string $customCss = null,
	) {
	}

	public static function fromArray(array $config): self
	{
		return new self(
			title: (string) ($config['title'] ?? 'Sign In'),
			primaryColor: (string) ($config['primaryColor'] ?? '#4a90d9'),
			primaryHoverColor: (string) ($config['primaryHoverColor'] ?? '#357abd'),
			backgroundColor: (string) ($config['backgroundColor'] ?? '#f5f5f5'),
			cardBackground: (string) ($config['cardBackground'] ?? '#ffffff'),
			textColor: (string) ($config['textColor'] ?? '#333333'),
			subtextColor: (string) ($config['subtextColor'] ?? '#555555'),
			logoUrl: isset($config['logoUrl']) ? (string) $config['logoUrl'] : null,
			logoAlt: isset($config['logoAlt']) ? (string) $config['logoAlt'] : null,
			faviconUrl: isset($config['faviconUrl']) ? (string) $config['faviconUrl'] : null,
			customCss: isset($config['customCss']) ? (string) $config['customCss'] : null,
		);
	}
}
