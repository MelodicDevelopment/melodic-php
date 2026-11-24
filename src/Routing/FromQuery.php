<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Attribute;

/**
 * Bind a Model-typed controller action parameter from the query string
 * instead of the request body — for GET endpoints with filter/paging DTOs:
 *
 *     public function index(#[FromQuery] UserFilter $filter): JsonResponse
 *
 * The model is coerced and validated exactly like a body-bound model.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class FromQuery
{
}
