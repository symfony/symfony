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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;
use Symfony\Component\Runtime\Runner\Middleware\MiddlewareInterface;
use Symfony\Component\Runtime\SymfonyRuntime;
use Symfony\Component\Runtime\Tests\Support\InvalidMiddleware;
use Symfony\Component\Runtime\Tests\Support\TestMiddleware;

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

    public function testGetRunnerWithMiddleware()
    {
        $application = $this->createStub(HttpKernelInterface::class);

        $_SERVER['FRANKENPHP_MIDDLEWARES'] = TestMiddleware::class;
        $runtime = new SymfonyRuntime();

        try {
            $_SERVER['FRANKENPHP_WORKER'] = 1;
            $runner = $runtime->getRunner($application);
            $this->assertInstanceOf(FrankenPhpWorkerRunner::class, $runner);

            $middlewaresProperty = new \ReflectionProperty($runner, 'middlewares');
            $middlewares = iterator_to_array($middlewaresProperty->getValue($runner));
            $this->assertCount(1, $middlewares);
            $this->assertInstanceOf(TestMiddleware::class, $middlewares[0]);
        } finally {
            restore_error_handler();
            restore_exception_handler();
            unset($_SERVER['FRANKENPHP_MIDDLEWARES']);
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

    public static function workerMiddlewaresOptionData(): iterable
    {
        yield 'empty middleware - null' => [
            'value' => null,
            'expectedWorkerMiddlewares' => [],
        ];

        yield 'empty middleware - empty' => [
            'value' => '',
            'expectedWorkerMiddlewares' => [],
        ];

        yield 'valid middleware - string' => [
            'value' => TestMiddleware::class,
            'expectedWorkerMiddlewares' => [TestMiddleware::class],
        ];

        yield 'valid middleware - array' => [
            'value' => [TestMiddleware::class],
            'expectedWorkerMiddlewares' => [TestMiddleware::class],
        ];

        yield 'invalid middleware' => [
            'value' => InvalidMiddleware::class,
            'expectedMessage' => \sprintf(
                'The middleware class "%s" must implement "%s"',
                InvalidMiddleware::class,
                MiddlewareInterface::class
            ),
        ];

        yield 'invalid middleware not a class' => [
            'value' => 'unknown',
            'expectedMessage' => \sprintf(
                'The middleware class "%s" must implement "%s"',
                'unknown',
                MiddlewareInterface::class
            ),
        ];

        yield 'invalid worker_middlewares option type - bool' => [
            'value' => false,
            'expectedMessage' => \sprintf('The "worker_middlewares" runtime option must be an array or string, "%s" given.', 'bool'),
        ];

        yield 'invalid worker_middlewares option type - int' => [
            'value' => 42,
            'expectedMessage' => \sprintf('The "worker_middlewares" runtime option must be an array or string, "%s" given.', 'int'),
        ];
    }

    #[DataProvider('workerMiddlewaresOptionData')]
    public function testWorkerMiddlewaresOption(
        mixed $value,
        ?array $expectedWorkerMiddlewares = null,
        ?string $expectedMessage = null,
    ) {
        if (null !== $expectedMessage) {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage($expectedMessage);
        }

        $runtime = new SymfonyRuntime(['worker_middlewares' => $value, 'error_handler' => false]);

        if (null !== $expectedWorkerMiddlewares) {
            $optionsReflection = new \ReflectionProperty($runtime, 'options');
            $workerMiddlewares = $optionsReflection->getValue($runtime)['worker_middlewares'] ?? [];
            $this->assertEquals($expectedWorkerMiddlewares, $workerMiddlewares);
        }
    }

    #[DataProvider('workerMiddlewaresOptionData')]
    public function testWorkerMiddlewaresEnv(
        mixed $value,
        ?array $expectedWorkerMiddlewares = null,
        ?string $expectedMessage = null,
    ) {
        if (null !== $expectedMessage) {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage($expectedMessage);
        }

        $_ENV['FRANKENPHP_MIDDLEWARES'] = (\is_array($value) ? implode("\n", $value) : $value);

        $runtime = new SymfonyRuntime(['error_handler' => false]);

        if (null !== $expectedWorkerMiddlewares) {
            $optionsReflection = new \ReflectionProperty($runtime, 'options');
            $workerMiddlewares = $optionsReflection->getValue($runtime)['worker_middlewares'] ?? [];
            $this->assertEquals($expectedWorkerMiddlewares, $workerMiddlewares);
        }
    }
}
