<?php

declare(strict_types=1);

namespace Melodic\Data;

use Attribute;

/**
 * Marks a Model property as never bindable from fromArray() input — the wire
 * value is silently ignored, so a client cannot over-post fields like `role`
 * or `isActive` on a reused entity DTO. Guarded properties can still be set
 * programmatically and still appear in toArray()/toPascalArray() output.
 *
 * Prefer dedicated request DTOs per endpoint; use #[Guarded] as defense in
 * depth when an entity model doubles as an input model.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Guarded
{
}
