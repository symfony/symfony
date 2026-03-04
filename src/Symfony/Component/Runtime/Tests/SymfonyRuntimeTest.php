<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;
use Symfony\Component\Runtime\RuntimeTypeResolverInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

class SymfonyRuntimeTest extends TestCase
{
    public function testGetRunner()
    {
        $application = $this->createStub(HttpKernelInterface::class);

        $runtime = new SymfonyRuntime();

        try {
            $this->assertNotInstanceOf(FrankenPhpWorkerRunner::class, $runtime->getRunner(null));
            $this->assertNotInstanceOf(FrankenPhpWorkerRunner::class, $runtime->getRunner($application));
            $_SERVER['FRANKENPHP_WORKER'] = 1;
            $this->assertInstanceOf(FrankenPhpWorkerRunner::class, $runtime->getRunner($application));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testStringWorkerMaxLoopThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "worker_loop_max" runtime option must be an integer, "string" given.');

        new SymfonyRuntime(['worker_loop_max' => 'foo']);
    }

    public function testBoolWorkerMaxLoopThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "worker_loop_max" runtime option must be an integer, "bool" given.');

        new SymfonyRuntime(['worker_loop_max' => false]);
    }

    public function testTypeResolverIsCalledForMappedType()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                TypeResolverFixture::class => SimpleTypeResolverFixture::class,
            ],
        ]);

        $result = $this->callResolveType($runtime, TypeResolverFixture::class);

        $this->assertInstanceOf(TypeResolverFixture::class, $result);
    }

    public function testTypeResolverIsLazilyInstantiated()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                TypeResolverFixture::class => CountsInstantiationResolverFixture::class,
            ],
        ]);

        $this->assertEquals(0, CountsInstantiationResolverFixture::$instantiationCount, 'Type resolver should not be instantiated until the type it resolves is requested.');

        $this->callResolveType($runtime, TypeResolverFixture::class);
        $this->callResolveType($runtime, TypeResolverFixture::class);

        $this->assertEquals(1, CountsInstantiationResolverFixture::$instantiationCount, 'Type resolver should only be instantiated once.');
    }

    public function testTypeResolverWithConstructorInjection()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                TypeResolverFixture::class => TypeResolverWithRequestFixture::class,
            ],
        ]);

        $result = $this->callResolveType($runtime, TypeResolverFixture::class);

        $this->assertInstanceOf(TypeResolverFixture::class, $result);
        $this->assertInstanceOf(Request::class, $result->injectedRequest);
    }

    public function testTypeResolverUnmappedTypeReturnsNull()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [],
        ]);

        $result = $this->callResolveType($runtime, TypeResolverFixture::class);

        $this->assertNull($result);
    }

    public function testTypeResolverNonExistentClassThrows()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                TypeResolverFixture::class => 'NonExistent\TypeResolver',
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Type resolver class "NonExistent\TypeResolver" does not exist.');

        $this->callResolveType($runtime, TypeResolverFixture::class);
    }

    public function testTypeResolverNotImplementingInterfaceThrows()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                TypeResolverFixture::class => \stdClass::class,
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Type resolver class "%s" must implement "%s".', \stdClass::class, RuntimeTypeResolverInterface::class));

        $this->callResolveType($runtime, TypeResolverFixture::class);
    }

    public function testTypeResolverCanOverrideBuiltInType()
    {
        $runtime = new SymfonyRuntime([
            'error_handler' => false,
            'type-resolvers' => [
                Request::class => OverridingRequestResolverFixture::class,
            ],
        ]);

        $result = $this->callResolveType($runtime, Request::class);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertSame('/custom', $result->getPathInfo());
    }

    private function callResolveType(SymfonyRuntime $runtime, string $type): mixed
    {
        $method = new \ReflectionMethod($runtime, 'resolveType');

        return $method->invoke($runtime, $type);
    }
}

/**
 * A simple value object used as the "type" to be resolved in tests.
 */
class TypeResolverFixture
{
    public function __construct(public readonly ?Request $injectedRequest = null)
    {
    }
}

/**
 * A type resolver with no constructor dependencies.
 */
class SimpleTypeResolverFixture implements RuntimeTypeResolverInterface
{
    private ?TypeResolverFixture $instance = null;

    public function resolveType(string $type): mixed
    {
        return $this->instance ??= new TypeResolverFixture();
    }
}

/**
 * A type resolver that requires a Request to be injected via constructor.
 */
class TypeResolverWithRequestFixture implements RuntimeTypeResolverInterface
{
    public function __construct(private readonly Request $request)
    {
    }

    public function resolveType(string $type): mixed
    {
        return new TypeResolverFixture($this->request);
    }
}

/**
 * A type resolver that overrides the built-in Request resolution.
 */
class OverridingRequestResolverFixture implements RuntimeTypeResolverInterface
{
    public function resolveType(string $type): mixed
    {
        return Request::create('/custom');
    }
}

/**
 * A type resolver that counts how often it was instantiated.
 */
class CountsInstantiationResolverFixture implements RuntimeTypeResolverInterface
{
    public static int $instantiationCount = 0;

    public function __construct()
    {
        ++self::$instantiationCount;
    }

    public function resolveType(string $type): mixed
    {
        return new TypeResolverFixture();
    }
}
