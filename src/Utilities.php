<?php

declare(strict_types=1);

namespace Melodic;

class Utilities
{
	/**
	 * Dump a variable and terminate the script.
	 *
	 * @param mixed $_var The variable to dump.
	 * @return never
	 */
	public static function kill(mixed $_var): never
	{
		print "<pre>";
		print_r($_var);
		print "</pre>";
		exit(1);
	}
}
