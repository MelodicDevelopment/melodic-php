<?php

declare(strict_types=1);

namespace Melodic\Security;

class OidcProvider
{
    public function __construct(
        private readonly string $discoveryUrl,
        private readonly string $cacheDir,
        private readonly int $cacheTtl = 3600,
    ) {
    }

    /** @return array<string, mixed> */
    public function discover(): array
    {
        return $this->fetchCached('oidc_discovery.json', $this->discoveryUrl);
    }

    /** @return array<string, mixed> */
    public function getJwks(): array
    {
        $discovery = $this->discover();
        $jwksUri = $discovery['jwks_uri'] ?? '';

        if ($jwksUri === '') {
            throw new SecurityException('OIDC discovery document missing jwks_uri.');
        }

        return $this->fetchCached('oidc_jwks.json', $jwksUri);
    }

    public function getIssuer(): string
    {
        $discovery = $this->discover();

        return $discovery['issuer']
            ?? throw new SecurityException('OIDC discovery document missing issuer.');
    }

    public function getAuthorizationEndpoint(): string
    {
        $discovery = $this->discover();

        return $discovery['authorization_endpoint']
            ?? throw new SecurityException('OIDC discovery document missing authorization_endpoint.');
    }

    public function getTokenEndpoint(): string
    {
        $discovery = $this->discover();

        return $discovery['token_endpoint']
            ?? throw new SecurityException('OIDC discovery document missing token_endpoint.');
    }

    /** @return array<string, mixed> */
    private function fetchCached(string $filename, string $url): array
    {
        // 0700: discovery/JWKS docs drive signature validation, so the cache must
        // not be writable by other local users (key-substitution would bypass it).
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir)) {
            throw new SecurityException("Unable to create OIDC cache directory: {$this->cacheDir}");
        }

        $cachePath = rtrim($this->cacheDir, '/') . '/' . $filename;

        if (file_exists($cachePath)) {
            $mtime = filemtime($cachePath);

            if ($mtime !== false && (time() - $mtime) < $this->cacheTtl) {
                $contents = file_get_contents($cachePath);

                if ($contents !== false) {
                    $decoded = json_decode($contents, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }

        $decoded = OAuthClient::requestJson('GET', $url);

        file_put_contents($cachePath, json_encode($decoded));

        return $decoded;
    }
}
