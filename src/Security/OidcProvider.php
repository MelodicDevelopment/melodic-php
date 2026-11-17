<?php

declare(strict_types=1);

namespace Melodic\Security;

class OidcProvider
{
    /** Minimum seconds between forced JWKS refreshes. */
    private const MIN_REFRESH_INTERVAL = 60;

    /** Seconds to keep serving a stale document before retrying a failed fetch. */
    private const STALE_RETRY_INTERVAL = 60;

    private bool $cacheDirChecked = false;

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
    /** @return array<string, mixed> */
    public function getJwks(): array
    {
        return $this->fetchCached('oidc_jwks.json', $this->jwksUri());
    }

    /**
     * Refetch the key set, bypassing the normal cache TTL — used when a token
     * is signed with a key id the cached set does not contain (the provider
     * rotated its keys). Throttled to one fetch per minute so a stream of
     * bogus tokens cannot force a network call per request.
     *
     * @return array<string, mixed>
     */
    public function refreshJwks(): array
    {
        return $this->fetchCached('oidc_jwks.json', $this->jwksUri(), self::MIN_REFRESH_INTERVAL);
    }

    private function jwksUri(): string
    {
        $jwksUri = $this->discover()['jwks_uri'] ?? '';

        if (!is_string($jwksUri) || $jwksUri === '') {
            throw new SecurityException('OIDC discovery document missing jwks_uri.');
        }

        return $jwksUri;
    }

    public function getIssuer(): string
    {
        $discovery = $this->discover();

        $issuer = $discovery['issuer']
            ?? throw new SecurityException('OIDC discovery document missing issuer.');

        // Sanity-check the advertised issuer against the host the discovery
        // document was fetched from — a compromised or misconfigured discovery
        // endpoint must not be able to pivot validation to a foreign issuer.
        $issuerHost = parse_url((string) $issuer, PHP_URL_HOST);
        $discoveryHost = parse_url($this->discoveryUrl, PHP_URL_HOST);

        if (!is_string($issuerHost) || $issuerHost !== $discoveryHost) {
            throw new SecurityException('OIDC issuer host does not match the discovery URL host.');
        }

        return (string) $issuer;
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

    /**
     * @param int|null $maxAge Treat a cache entry older than this many seconds
     *                         as stale (defaults to the configured TTL).
     * @return array<string, mixed>
     */
    private function fetchCached(string $filename, string $url, ?int $maxAge = null): array
    {
        $this->prepareCacheDir();

        $cachePath = rtrim($this->cacheDir, '/') . '/' . $filename;
        $cached = $this->readCache($cachePath);

        if ($cached !== null) {
            $mtime = filemtime($cachePath);

            if ($mtime !== false && (time() - $mtime) < ($maxAge ?? $this->cacheTtl)) {
                return $cached;
            }
        }

        try {
            $decoded = $this->fetchJson($url);
        } catch (SecurityException $e) {
            // The provider is unreachable or erroring. A stale document is
            // far better than failing every request until it recovers, so
            // serve the last good copy and retry after a short pause.
            if ($cached !== null) {
                @touch($cachePath, time() - $this->cacheTtl + self::STALE_RETRY_INTERVAL);

                return $cached;
            }

            throw $e;
        }

        // Write-then-rename so a concurrent request never reads a half-written file.
        $tmp = $cachePath . '.' . getmypid() . '.tmp';

        if (@file_put_contents($tmp, json_encode($decoded), LOCK_EX) !== false) {
            @chmod($tmp, 0600);

            if (!@rename($tmp, $cachePath)) {
                @unlink($tmp);
            }
        }

        return $decoded;
    }

    /**
     * Network fetch, separated so tests can stub it.
     *
     * @return array<string, mixed>
     */
    protected function fetchJson(string $url): array
    {
        return OAuthClient::requestJson('GET', $url);
    }

    /** @return array<string, mixed>|null */
    private function readCache(string $cachePath): ?array
    {
        if (!is_file($cachePath)) {
            return null;
        }

        $contents = @file_get_contents($cachePath);
        $decoded = $contents !== false ? json_decode($contents, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * 0700: discovery/JWKS documents drive signature validation, so the cache
     * must not be writable by other local users (a planted key set would
     * bypass it).
     */
    private function prepareCacheDir(): void
    {
        if ($this->cacheDirChecked) {
            return;
        }

        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir)) {
            throw new SecurityException("Unable to create OIDC cache directory: {$this->cacheDir}");
        }

        // A directory that already existed may not be ours. The default
        // location is under the shared system temp dir, where any local user
        // can pre-create it — so there it must be owned by this process and
        // closed to group/other. A directory the operator configured
        // elsewhere is trusted as deployed.
        $tempRoot = rtrim((string) realpath(sys_get_temp_dir()), '/');
        $realDir = (string) realpath($this->cacheDir);

        if ($tempRoot !== '' && str_starts_with($realDir . '/', $tempRoot . '/')) {
            clearstatcache(true, $this->cacheDir);

            if (function_exists('posix_geteuid') && fileowner($this->cacheDir) !== posix_geteuid()) {
                throw new SecurityException(
                    "OIDC cache directory {$this->cacheDir} is owned by another user. "
                    . 'Set auth.oidcCacheDir to an application-owned directory.'
                );
            }

            $perms = fileperms($this->cacheDir);

            if ($perms !== false && ($perms & 0077) !== 0 && !@chmod($this->cacheDir, 0700)) {
                throw new SecurityException("Unable to restrict OIDC cache directory permissions: {$this->cacheDir}");
            }
        }

        $this->cacheDirChecked = true;
    }
}
