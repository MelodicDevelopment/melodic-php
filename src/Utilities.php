<?php

declare(strict_types=1);

namespace Melodic;

class Utilities
{
	/**
	 * Dump a variable and terminate the script.
	 *
	 * Debugging aid only — it hard-exits and writes HTML, so it must never be
	 * left in a request path in production. Output is HTML-escaped so dumping
	 * user-supplied data cannot inject markup/script.
	 *
	 * @param mixed $_var The variable to dump.
	 * @return never
	 */
	public static function kill(mixed $_var): never
	{
		print "<pre>";
		print htmlspecialchars(print_r($_var, true), ENT_QUOTES, 'UTF-8');
		print "</pre>";
		exit(1);
	}
}
