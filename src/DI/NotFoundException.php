<?php

declare(strict_types=1);

namespace Melodic\DI;

/**
 * Thrown when an id is neither bound nor an existing class (PSR-11's
 * "not found" case). Extends ContainerException, so existing catch blocks
 * and the auto-wiring default-value fallback keep working.
 */
class NotFoundException extends ContainerException implements \Psr\Container\NotFoundExceptionInterface
{
}
