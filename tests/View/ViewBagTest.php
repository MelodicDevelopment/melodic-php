<?php

declare(strict_types=1);

namespace Tests\View;

use Melodic\View\ViewBag;
use PHPUnit\Framework\TestCase;

final class ViewBagTest extends TestCase
{
    public function testSetAndGetProperty(): void
    {
        $bag = new ViewBag();

        $bag->title = 'Hello';

        $this->assertSame('Hello', $bag->title);
    }

    public function testGetUndefinedPropertyReturnsNull(): void
    {
        $bag = new ViewBag();

        $this->assertNull($bag->nonexistent);
    }

    public function testIsset(): void
    {
        $bag = new ViewBag();

        $this->assertFalse(isset($bag->title));

        $bag->title = 'Hello';

        $this->assertTrue(isset($bag->title));
    }

    public function testIssetReturnsFalseForNullValue(): void
    {
        $bag = new ViewBag();

        $bag->value = null;

        $this->assertFalse(isset($bag->value));
    }

    public function testToArray(): void
    {
        $bag = new ViewBag();
        $bag->title = 'Hello';
        $bag->count = 42;

        $result = $bag->toArray();

        $this->assertSame(['title' => 'Hello', 'count' => 42], $result);
    }

    public function testToArrayEmptyByDefault(): void
    {
        $bag = new ViewBag();

        $this->assertSame([], $bag->toArray());
    }

    public function testOverwriteProperty(): void
    {
        $bag = new ViewBag();

        $bag->title = 'First';
        $bag->title = 'Second';

        $this->assertSame('Second', $bag->title);
    }
}
