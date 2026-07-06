<?php

declare(strict_types=1);

namespace Melodic\DI;

use RuntimeException;

/**
 * Thrown when the container itself cannot resolve an id (unknown class,
 * non-instantiable class, unresolvable constructor parameter). Extends
 * RuntimeException so existing catch blocks keep working. Exceptions thrown
 * *by user code* (factories, constructors) are never wrapped in this type,
 * which lets auto-wiring distinguish "nothing is bound" from "the binding
 * blew up" — only the former may fall back to a parameter's default value.
 */
class ContainerException extends RuntimeException
{
}
