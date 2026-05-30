<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Request;
use Melodic\Security\AuthProviderConfig;
use Melodic\Security\AuthProviderType;
use Melodic\Security\ClaimMapper;
use Melodic\Security\LocalAuthConfig;
use Melodic\Security\OAuth2AuthProvider;
use Melodic\Security\SessionManager;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class OAuth2AuthProviderTest extends TestCase
{
    private function provider(): OAuth2AuthProvider
    {
        $config = new AuthProviderConfig(
            name: 'acme',
            type: AuthProviderType::OAuth2,
            authorizeUrl: 'https://idp.example.com/authorize',
            tokenUrl: 'https://idp.example.com/token',
            userInfoUrl: 'https://idp.example.com/userinfo',
            clientId: 'client-123',
            clientSecret: 'secret',
            redirectUri: 'https://app.example.com/auth/callback',
            scopes: 'profile email',
        );

        $local = new LocalAuthConfig(signingKey: 'a-sufficiently-long-signing-secret-32+');

        return new OAuth2AuthProvider($config, $local, new ClaimMapper());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginUrlIncludesResponseTypeAndPkce(): void
    {
        $response = $this->provider()->handleLogin(new Request(), new SessionManager());

        $location = $response->getHeaders()['Location'] ?? '';

        $this->assertStringContainsString('response_type=code', $location);
        $this->assertStringContainsString('code_challenge=', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('client_id=client-123', $location);
    }
}
