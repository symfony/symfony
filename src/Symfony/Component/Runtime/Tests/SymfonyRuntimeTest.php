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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;
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
}
