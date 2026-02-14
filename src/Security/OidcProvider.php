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

    public function discover(): array
    {
        return $this->fetchCached('oidc_discovery.json', $this->discoveryUrl);
    }

    public function getJwks(): array
    {
        $discovery = $this->discover();
        $jwksUri = $discovery['jwks_uri'] ?? '';

        if ($jwksUri === '') {
            throw new SecurityException('OIDC discovery document missing jwks_uri.');
        }

        return $this->fetchCached('oidc_jwks.json', $jwksUri);
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

    private function fetchCached(string $filename, string $url): array
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
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

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $contents = file_get_contents($url, false, $context);

        if ($contents === false) {
            throw new SecurityException("Failed to fetch OIDC document from: {$url}");
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new SecurityException("Invalid JSON response from: {$url}");
        }

        file_put_contents($cachePath, $contents);

        return $decoded;
    }
}
