<?php

declare(strict_types=1);

namespace Melodic\Data;

use Attribute;

/**
 * Declares the element type of an `array` Model property so fromArray()
 * hydrates each element into a Model (and validation then covers them):
 *
 *     #[ListOf(OrderLine::class)]
 *     public array $Lines = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ListOf
{
    /** @param class-string<Model> $class */
    public function __construct(public readonly string $class)
    {
    }
}
