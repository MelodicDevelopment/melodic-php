<?php

declare(strict_types=1);

namespace Tests\DI;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// --- Test fixtures ---

class SimpleClass
{
    public string $value = 'simple';
}

interface TestServiceInterface
{
    public function getValue(): string;
}

class TestServiceImplementation implements TestServiceInterface
{
    public function getValue(): string
    {
        return 'implementation';
    }
}

class ClassWithDependency
{
    public function __construct(
        public readonly SimpleClass $simple
    ) {}
}

class ClassWithNestedDependencies
{
    public function __construct(
        public readonly ClassWithDependency $dep
    ) {}
}

class ClassWithDefaultParam
{
    public function __construct(
        public readonly string $name = 'default'
    ) {}
}

class ClassWithUntypedParam
{
    public mixed $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class CircularA
{
    public function __construct(public readonly CircularB $b) {}
}

class CircularB
{
    public function __construct(public readonly CircularA $a) {}
}

abstract class AbstractNonInstantiable
{
    abstract public function doSomething(): void;
}

class TestProvider extends ServiceProvider
{
    public bool $booted = false;

    public function register(Container $container): void
    {
        $container->bind(TestServiceInterface::class, TestServiceImplementation::class);
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
    }
}

// --- Tests ---

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testGetResolvesConcreteClass(): void
    {
        $instance = $this->container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
        $this->assertSame('simple', $instance->value);
    }

    public function testGetReturnsNewInstanceEachTimeForUnboundClass(): void
    {
        $a = $this->container->get(SimpleClass::class);
        $b = $this->container->get(SimpleClass::class);

        $this->assertNotSame($a, $b);
    }

    public function testBindMapsInterfaceToImplementation(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);

        $instance = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestServiceImplementation::class, $instance);
        $this->assertSame('implementation', $instance->getValue());
    }

    public function testBindReturnsNewInstanceEachTime(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);

        $a = $this->container->get(TestServiceInterface::class);
        $b = $this->container->get(TestServiceInterface::class);

        $this->assertNotSame($a, $b);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(TestServiceInterface::class, TestServiceImplementation::class);

        $a = $this->container->get(TestServiceInterface::class);
        $b = $this->container->get(TestServiceInterface::class);

        $this->assertSame($a, $b);
    }

    public function testSingletonWithFactyClosure(): void
    {
        $this->container->singleton(TestServiceInterface::class, function (Container $c) {
            return new TestServiceImplementation();
        });

        $a = $this->container->get(TestServiceInterface::class);
        $b = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestServiceImplementation::class, $a);
        $this->assertSame($a, $b);
    }

    public function testAutoWiringResolvesConstructorDependencies(): void
    {
        $instance = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->simple);
    }

    public function testAutoWiringResolvesNestedDependencies(): void
    {
        $instance = $this->container->get(ClassWithNestedDependencies::class);

        $this->assertInstanceOf(ClassWithNestedDependencies::class, $instance);
        $this->assertInstanceOf(ClassWithDependency::class, $instance->dep);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep->simple);
    }

    public function testAutoWiringUsesBindingsForDependencies(): void
    {
        $this->container->bind(SimpleClass::class, SimpleClass::class);

        $instance = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(SimpleClass::class, $instance->simple);
    }

    public function testCircularDependencyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->get(CircularA::class);
    }

    public function testFactoryClosureReceivesContainer(): void
    {
        $this->container->singleton(SimpleClass::class, SimpleClass::class);

        $this->container->bind(ClassWithDependency::class, function (Container $c) {
            $simple = $c->get(SimpleClass::class);
            return new ClassWithDependency($simple);
        });

        $instance = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->simple);
    }

    public function testBindWithFactoryClosure(): void
    {
        $counter = 0;
        $this->container->bind(SimpleClass::class, function () use (&$counter) {
            $counter++;
            $obj = new SimpleClass();
            $obj->value = "created-{$counter}";
            return $obj;
        });

        $a = $this->container->get(SimpleClass::class);
        $b = $this->container->get(SimpleClass::class);

        $this->assertSame('created-1', $a->value);
        $this->assertSame('created-2', $b->value);
    }

    public function testInstanceRegistersPrebuiltObject(): void
    {
        $obj = new SimpleClass();
        $obj->value = 'prebuilt';
        $this->container->instance(SimpleClass::class, $obj);

        $resolved = $this->container->get(SimpleClass::class);

        $this->assertSame($obj, $resolved);
        $this->assertSame('prebuilt', $resolved->value);
    }

    public function testHasReturnsTrueForBoundClass(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);

        $this->assertTrue($this->container->has(TestServiceInterface::class));
    }

    public function testHasReturnsTrueForExistingClass(): void
    {
        $this->assertTrue($this->container->has(SimpleClass::class));
    }

    public function testHasReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse($this->container->has('NonExistent\\ClassName'));
    }

    public function testHasReturnsTrueForRegisteredInstance(): void
    {
        $this->container->instance('myService', new SimpleClass());

        $this->assertTrue($this->container->has('myService'));
    }

    public function testGetUnresolvableClassThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('class does not exist');

        $this->container->get('Nonexistent\\ClassName');
    }

    public function testGetNonInstantiableClassThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not instantiable');

        $this->container->get(AbstractNonInstantiable::class);
    }

    public function testParameterWithNoTypeHintAndNoDefaultThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no type hint and no default value');

        $this->container->get(ClassWithUntypedParam::class);
    }

    public function testClassWithDefaultParameterValuesResolves(): void
    {
        $instance = $this->container->get(ClassWithDefaultParam::class);

        $this->assertInstanceOf(ClassWithDefaultParam::class, $instance);
        $this->assertSame('default', $instance->name);
    }

    public function testServiceProviderRegistration(): void
    {
        $provider = new TestProvider();
        $provider->register($this->container);

        $instance = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestServiceImplementation::class, $instance);
    }

    public function testServiceProviderBoot(): void
    {
        $provider = new TestProvider();
        $provider->register($this->container);
        $provider->boot($this->container);

        $this->assertTrue($provider->booted);
    }

    public function testBindOverridesPreviousBinding(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);
        $this->container->bind(TestServiceInterface::class, function () {
            $obj = new TestServiceImplementation();
            return $obj;
        });

        $instance = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestServiceImplementation::class, $instance);
    }

    public function testBindClearsCachedSingletonInstance(): void
    {
        $this->container->singleton(SimpleClass::class, function () {
            $obj = new SimpleClass();
            $obj->value = 'first';
            return $obj;
        });

        $first = $this->container->get(SimpleClass::class);
        $this->assertSame('first', $first->value);

        $this->container->bind(SimpleClass::class, function () {
            $obj = new SimpleClass();
            $obj->value = 'second';
            return $obj;
        });

        $second = $this->container->get(SimpleClass::class);
        $this->assertSame('second', $second->value);
    }
}
