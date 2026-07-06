<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    public function testDestroyExpiresTheSessionCookie(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = 'require ' . var_export($autoload, true) . ';
            $s = new \Melodic\Security\SessionManager(cookieSecure: false);
            $s->set("user", "alice");
            $s->destroy();
            echo function_exists("xdebug_get_headers") ? implode("\n", xdebug_get_headers()) : "NO_XDEBUG";';

        // Header inspection needs xdebug_get_headers(), which does not observe
        // headers inside PHPUnit's own (isolated) processes — hence a subprocess.
        $output = (string) shell_exec(
            escapeshellarg(PHP_BINARY)
            . ' -d xdebug.mode=develop -d xdebug.start_with_request=no -r '
            . escapeshellarg($script) . ' 2>/dev/null',
        );

        if (str_contains($output, 'NO_XDEBUG')) {
            $this->markTestSkipped('xdebug is required to inspect emitted headers.');
        }

        // PHP renders an empty-value cookie with a past expiry as "name=deleted".
        $this->assertStringContainsString(
            'PHPSESSID=deleted',
            $output,
            'destroy() must emit an expiring Set-Cookie for the session cookie',
        );
    }
}
