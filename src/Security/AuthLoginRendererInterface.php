<?php

declare(strict_types=1);

namespace Melodic\Security;

interface AuthLoginRendererInterface
{
	/**
	 * Render the login page HTML.
	 *
	 * @param string|null $error Optional error message to display.
	 * @return string Full HTML response body.
	 */
	public function render(?string $error = null): string;
}
