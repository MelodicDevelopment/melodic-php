<?php

declare(strict_types=1);

namespace Melodic\DI;

/**
 * Thrown when auto-wiring detects a dependency cycle. Never swallowed by
 * default-value fallbacks — a cycle is a structural bug, not a missing binding.
 */
class CircularDependencyException extends ContainerException
{
}
