<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test;

require_once __DIR__.'/../Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\GcFunctionMock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        GcFunctionMock::$status = null;

        parent::tearDown();
    }

    #[TestWith([5000, 0])]
    #[TestWith([5001, 1])]
    public function testEnsureKernelShutdownCollectsCyclesWhenTheRootBufferIsHalfFull(int $roots, int $collections)
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getContainer')->willReturn(new Container());
        $kernel->expects($this->once())->method('shutdown');
        static::$kernel = $kernel;
        GcFunctionMock::$status = ['roots' => $roots, 'threshold' => 10001];
        GcFunctionMock::$collections = 0;

        static::ensureKernelShutdown();
        static::$kernel = null;

        $this->assertSame($collections, GcFunctionMock::$collections);
    }
}
