<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Request;
use Melodic\Http\Response;

class LocalAuthProvider implements AuthProviderInterface
{
    public function __construct(
        private readonly AuthProviderConfig $config,
        private readonly LocalAuthConfig $localAuthConfig,
        private readonly LocalAuthenticatorInterface $authenticator,
    ) {
    }

    public function getName(): string
    {
        return $this->config->name;
    }

    public function getLabel(): string
    {
        return $this->config->label;
    }

    public function getType(): AuthProviderType
    {
        return AuthProviderType::Local;
    }

    public function handleLogin(Request $request, SessionManager $session): Response
    {
        throw new SecurityException('Local provider does not support redirect-based login. Use POST to the callback endpoint.');
    }

    public function handleCallback(Request $request, SessionManager $session): AuthResult
    {
        $username = (string) $request->body('username', '');
        $password = (string) $request->body('password', '');

        if ($username === '' || $password === '') {
            throw new SecurityException('Username and password are required.');
        }

        $claims = $this->authenticator->authenticate($username, $password);
        $claims['provider'] = $this->config->name;

        $token = LocalJwtIssuer::issue($this->localAuthConfig, $claims, $this->config->name);

        return new AuthResult(
            token: $token,
            claims: $claims,
            providerName: $this->config->name,
        );
    }
}
