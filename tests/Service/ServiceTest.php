<?php

declare(strict_types=1);

namespace Tests\Service;

use Melodic\Data\DbContextInterface;
use Melodic\Service\Service;
use PHPUnit\Framework\TestCase;

final class TestableService extends Service
{
    public function exposedGetContext(): DbContextInterface
    {
        return $this->getContext();
    }

    public function exposedGetReadOnlyContext(): DbContextInterface
    {
        return $this->getReadOnlyContext();
    }
}

final class ServiceTest extends TestCase
{
    public function testGetContextReturnsPrimaryContext(): void
    {
        $context = $this->createMock(DbContextInterface::class);
        $service = new TestableService($context);

        $this->assertSame($context, $service->exposedGetContext());
    }

    public function testGetReadOnlyContextReturnsPrimaryContextWhenNoReadOnlyProvided(): void
    {
        $context = $this->createMock(DbContextInterface::class);
        $service = new TestableService($context);

        $this->assertSame($context, $service->exposedGetReadOnlyContext());
    }

    public function testGetReadOnlyContextReturnsReadOnlyContextWhenProvided(): void
    {
        $primaryContext = $this->createMock(DbContextInterface::class);
        $readOnlyContext = $this->createMock(DbContextInterface::class);
        $service = new TestableService($primaryContext, $readOnlyContext);

        $this->assertSame($readOnlyContext, $service->exposedGetReadOnlyContext());
    }
}
