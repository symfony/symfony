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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\RawInputInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;
use Symfony\Component\Runtime\SymfonyRuntime;

class SymfonyRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_WORKER'], $_SERVER['FRANKENPHP_LOOP_MAX']);
    }

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

    #[TestWith(['0'])]
    #[TestWith([''])]
    #[TestWith(['false'])]
    #[TestWith(['off'])]
    public function testFalsyFrankenPhpWorkerDoesNotEnableWorkerRunner(string $value)
    {
        $application = $this->createStub(HttpKernelInterface::class);
        $_SERVER['FRANKENPHP_WORKER'] = $value;

        $runtime = new SymfonyRuntime();

        try {
            $this->assertNotInstanceOf(FrankenPhpWorkerRunner::class, $runtime->getRunner($application));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testFrankenPhpLoopMaxEnvVarFallback()
    {
        $_SERVER['FRANKENPHP_LOOP_MAX'] = '42';

        $runtime = new SymfonyRuntime();

        $r = new \ReflectionProperty($runtime, 'options');
        $options = $r->getValue($runtime);

        try {
            $this->assertSame(42, $options['worker_loop_max']);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testFrankenPhpLoopMaxEnvVarInvalidThrows()
    {
        $_SERVER['FRANKENPHP_LOOP_MAX'] = 'not-a-number';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "worker_loop_max" runtime option must be an integer, "string" given.');

        new SymfonyRuntime();
    }

    public function testResolveTypeOverrideCanInjectCustomInstances()
    {
        $runtime = new class extends SymfonyRuntime {
            public \stdClass $customInput;

            public function __construct()
            {
                parent::__construct();
                $this->customInput = new \stdClass();
            }

            protected function resolveType(string $type): mixed
            {
                if (InputInterface::class === $type) {
                    return $this->customInput;
                }

                return parent::resolveType($type);
            }
        };

        try {
            $resolver = $runtime->getResolver(static fn (InputInterface $input) => $input);
            [$callable, $args] = $resolver->resolve();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        $this->assertSame([$runtime->customInput], $args);
    }

    public function testResolveTypeReturningNullDelegatesToParent()
    {
        $runtime = new class extends SymfonyRuntime {
            protected function resolveType(string $type): mixed
            {
                return null;
            }
        };

        try {
            $resolver = $runtime->getResolver(static fn (RawInputInterface $input) => $input);
            $this->expectException(\InvalidArgumentException::class);
            $resolver->resolve();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testRawInputInterfaceIsResolved()
    {
        $runtime = new SymfonyRuntime();

        try {
            $resolver = $runtime->getResolver(static fn (RawInputInterface $input) => $input);
            [$callable, $args] = $resolver->resolve();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        $this->assertCount(1, $args);
        $this->assertInstanceOf(RawInputInterface::class, $args[0]);
    }

    public function testUntypedRequiredArgumentMessageMentionsUntyped()
    {
        $runtime = new SymfonyRuntime();

        try {
            $resolver = $runtime->getResolver(static fn ($untyped) => $untyped);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/Cannot resolve untyped argument "\$untyped"/');
            $resolver->resolve();
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

    public function testUntypedOptionalArgumentDoesNotCrash()
    {
        $runtime = new SymfonyRuntime();
        $captured = null;

        try {
            $resolver = $runtime->getResolver(static function ($untyped = 'default') use (&$captured) {
                $captured = $untyped;
            });
            [$callable, $args] = $resolver->resolve();
            $callable(...$args);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        $this->assertSame([], $args);
        $this->assertSame('default', $captured);
    }

    public function testUntypedRequiredArgumentThrowsInvalidArgument()
    {
        $runtime = new SymfonyRuntime();

        try {
            $resolver = $runtime->getResolver(static fn ($untyped) => $untyped);
            $this->expectException(\InvalidArgumentException::class);
            $resolver->resolve();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
