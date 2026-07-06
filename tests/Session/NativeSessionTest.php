<?php

declare(strict_types=1);

namespace Tests\Session;

use PHPUnit\Framework\TestCase;

class NativeSessionTest extends TestCase
{
    public function testDestroyExpiresTheSessionCookie(): void
    {
        $headers = $this->headersEmittedBy(
            '$s = new \Melodic\Session\NativeSession(cookieSecure: false);
             $s->set("user", "alice");
             $s->destroy();',
        );

        // PHP renders an empty-value cookie with a past expiry as "name=deleted".
        $this->assertStringContainsString(
            'PHPSESSID=deleted',
            $headers,
            'destroy() must emit an expiring Set-Cookie for the session cookie',
        );
    }

    public function testDestroyOnUnstartedSessionEmitsNoCookie(): void
    {
        $headers = $this->headersEmittedBy(
            '$s = new \Melodic\Session\NativeSession(cookieSecure: false);
             $s->destroy();',
        );

        $this->assertStringNotContainsString('Set-Cookie', $headers);
    }

    /**
     * Run a snippet in a fresh PHP process and return the headers it emitted.
     * Header inspection needs xdebug_get_headers(), which does not observe
     * headers inside PHPUnit's own (isolated) processes — hence the subprocess.
     */
    private function headersEmittedBy(string $code): string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = 'require ' . var_export($autoload, true) . '; ' . $code
            . '; echo function_exists("xdebug_get_headers") ? implode("\n", xdebug_get_headers()) : "NO_XDEBUG";';

        $output = (string) shell_exec(
            escapeshellarg(PHP_BINARY)
            . ' -d xdebug.mode=develop -d xdebug.start_with_request=no -r '
            . escapeshellarg($script) . ' 2>/dev/null',
        );

        if (str_contains($output, 'NO_XDEBUG')) {
            $this->markTestSkipped('xdebug is required to inspect emitted headers.');
        }

        return $output;
    }
}
