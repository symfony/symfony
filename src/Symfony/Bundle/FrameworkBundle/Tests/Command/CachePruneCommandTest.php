<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\KernelInterface;

class CachePruneCommandTest extends TestCase
{
    public function testCommandWithPools()
    {
        $tester = $this->getCommandTester($this->getKernel(), $this->getRewindableGenerator());
        $tester->execute([]);
    }

    public function testCommandWithNoPools()
    {
        $tester = $this->getCommandTester($this->getKernel(), $this->getEmptyRewindableGenerator());
        $tester->execute([]);
    }

    public function testCommandFailsOnPruneError()
    {
        $failedPool = $this->createMock(PruneableInterface::class);
        $failedPool->expects($this->once())->method('prune')->willReturn(false);

        $generator = new RewindableGenerator(static function () use ($failedPool) {
            yield 'failed_pool' => $failedPool;
        }, 1);

        $tester = $this->getCommandTester($this->getKernel(), $generator);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('[ERROR] Cache pool "failed_pool" could not be pruned.', $tester->getDisplay());
    }

    public function testCommandContinuesOnFailure()
    {
        $failedPool = $this->createMock(PruneableInterface::class);
        $failedPool->expects($this->once())->method('prune')->willReturn(false);

        $successPool = $this->createMock(PruneableInterface::class);
        $successPool->expects($this->once())->method('prune')->willReturn(true);

        $generator = new RewindableGenerator(static function () use ($failedPool, $successPool) {
            yield 'failed_pool' => $failedPool;
            yield 'success_pool' => $successPool;
        }, 2);

        $tester = $this->getCommandTester($this->getKernel(), $generator);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('[ERROR] Cache pool "failed_pool" could not be pruned.', $display);
        $this->assertStringContainsString('Pruning cache pool: success_pool', $display);
    }

    private function getRewindableGenerator(): RewindableGenerator
    {
        return new RewindableGenerator(function () {
            yield 'foo_pool' => $this->getPruneableInterfaceMock();
            yield 'bar_pool' => $this->getPruneableInterfaceMock();
        }, 2);
    }

    private function getEmptyRewindableGenerator(): RewindableGenerator
    {
        return new RewindableGenerator(static fn () => new \ArrayIterator([]), 0);
    }

    private function getKernel(): MockObject&KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn(new Container());

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }

    private function getPruneableInterfaceMock(): MockObject&PruneableInterface
    {
        $pruneable = $this->createMock(PruneableInterface::class);
        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune');

        return $pruneable;
    }

    private function getCommandTester(KernelInterface $kernel, RewindableGenerator $generator): CommandTester
    {
        $application = new Application($kernel);
        $application->addCommand(new CachePoolPruneCommand($generator));

        return new CommandTester($application->find('cache:pool:prune'));
    }
}
