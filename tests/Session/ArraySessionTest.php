<?php

declare(strict_types=1);

namespace Tests\Session;

use Melodic\Session\ArraySession;
use Melodic\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

class ArraySessionTest extends TestCase
{
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
    }

    public function testImplementsSessionInterface(): void
    {
        $this->assertInstanceOf(SessionInterface::class, $this->session);
    }

    public function testSetAndGet(): void
    {
        $this->session->set('name', 'Alice');

        $this->assertSame('Alice', $this->session->get('name'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertNull($this->session->get('missing'));
        $this->assertSame('fallback', $this->session->get('missing', 'fallback'));
    }

    public function testGetReturnsStoredValueOverDefault(): void
    {
        $this->session->set('key', 'value');

        $this->assertSame('value', $this->session->get('key', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->session->set('exists', true);

        $this->assertTrue($this->session->has('exists'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->session->has('missing'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $this->session->set('nullable', null);

        $this->assertTrue($this->session->has('nullable'));
    }

    public function testRemoveDeletesKey(): void
    {
        $this->session->set('key', 'value');
        $this->session->remove('key');

        $this->assertFalse($this->session->has('key'));
        $this->assertNull($this->session->get('key'));
    }

    public function testRemoveNonExistentKeyDoesNotError(): void
    {
        $this->session->remove('nonexistent');

        $this->assertFalse($this->session->has('nonexistent'));
    }

    public function testDestroyClearsAllData(): void
    {
        $this->session->set('a', 1);
        $this->session->set('b', 2);
        $this->session->destroy();

        $this->assertFalse($this->session->has('a'));
        $this->assertFalse($this->session->has('b'));
        $this->assertNull($this->session->get('a'));
    }

    public function testDestroyResetsStartedState(): void
    {
        $this->session->start();
        $this->assertTrue($this->session->isStarted());

        $this->session->destroy();
        $this->assertFalse($this->session->isStarted());
    }

    public function testStartSetsStartedState(): void
    {
        $this->assertFalse($this->session->isStarted());

        $this->session->start();

        $this->assertTrue($this->session->isStarted());
    }

    public function testSetImplicitlyStartsSession(): void
    {
        $this->session->set('key', 'value');

        $this->assertTrue($this->session->isStarted());
    }

    public function testRegenerateDoesNotClearData(): void
    {
        $this->session->set('key', 'value');
        $this->session->regenerate();

        $this->assertSame('value', $this->session->get('key'));
    }

    public function testStoresVariousTypes(): void
    {
        $this->session->set('int', 42);
        $this->session->set('float', 3.14);
        $this->session->set('bool', false);
        $this->session->set('array', [1, 2, 3]);
        $this->session->set('null', null);

        $this->assertSame(42, $this->session->get('int'));
        $this->assertSame(3.14, $this->session->get('float'));
        $this->assertFalse($this->session->get('bool'));
        $this->assertSame([1, 2, 3], $this->session->get('array'));
        $this->assertNull($this->session->get('null'));
    }

    public function testOverwriteExistingKey(): void
    {
        $this->session->set('key', 'first');
        $this->session->set('key', 'second');

        $this->assertSame('second', $this->session->get('key'));
    }
}
