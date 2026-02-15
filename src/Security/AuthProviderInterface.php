<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Request;
use Melodic\Http\Response;

interface AuthProviderInterface
{
    public function getName(): string;

    public function getLabel(): string;

    public function getType(): AuthProviderType;

    public function handleLogin(Request $request, SessionManager $session): Response;

    public function handleCallback(Request $request, SessionManager $session): AuthResult;
}
