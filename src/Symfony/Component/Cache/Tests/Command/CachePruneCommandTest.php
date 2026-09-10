<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Command\CachePoolPruneCommand;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class CachePruneCommandTest extends TestCase
{
    public function testCommandWithPools()
    {
        $tester = $this->getCommandTester($this->getRewindableGenerator());
        $tester->execute([]);

        $this->assertStringContainsString('Pruning cache pool: foo_pool', $tester->getDisplay());
        $this->assertStringContainsString('Pruning cache pool: bar_pool', $tester->getDisplay());
    }

    public function testCommandWithNoPools()
    {
        $tester = $this->getCommandTester($this->getEmptyRewindableGenerator());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Successfully pruned cache pool(s).', $tester->getDisplay());
    }

    public function testCommandFailsOnPruneError()
    {
        $failedPool = $this->createMock(PruneableInterface::class);
        $failedPool->expects($this->once())->method('prune')->willReturn(false);

        $generator = new RewindableGenerator(static function () use ($failedPool) {
            yield 'failed_pool' => $failedPool;
        }, 1);

        $tester = $this->getCommandTester($generator);
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

        $tester = $this->getCommandTester($generator);
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

    private function getPruneableInterfaceMock(): MockObject&PruneableInterface
    {
        $pruneable = $this->createMock(PruneableInterface::class);
        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune');

        return $pruneable;
    }

    private function getCommandTester(RewindableGenerator $generator): CommandTester
    {
        $application = new Application();
        $application->addCommand(new CachePoolPruneCommand($generator));

        return new CommandTester($application->find('cache:pool:prune'));
    }
}
