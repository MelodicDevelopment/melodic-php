<?php

declare(strict_types=1);

namespace Melodic\Controller;

use Melodic\Security\UserContextInterface;

class ApiController extends Controller
{
    protected function getUserContext(): ?UserContextInterface
    {
        return $this->request->getAttribute('userContext');
    }
}
