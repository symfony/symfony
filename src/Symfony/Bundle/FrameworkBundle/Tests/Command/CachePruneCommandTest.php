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

use Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
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

    private function getRewindableGenerator(): RewindableGenerator
    {
        return new RewindableGenerator(function () {
            yield 'foo_pool' => $this->getPruneableInterfaceMock();
            yield 'bar_pool' => $this->getPruneableInterfaceMock();
        }, 2);
    }

    private function getEmptyRewindableGenerator(): RewindableGenerator
    {
        return new RewindableGenerator(function () {
            return new \ArrayIterator([]);
        }, 0);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|KernelInterface
     */
    private function getKernel()
    {
        $container = $this
            ->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')
            ->getMock();

        $kernel = $this
            ->getMockBuilder(KernelInterface::class)
            ->getMock();

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|PruneableInterface
     */
    private function getPruneableInterfaceMock()
    {
        $pruneable = $this
            ->getMockBuilder(PruneableInterface::class)
            ->getMock();

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune');

        return $pruneable;
    }

    private function getCommandTester(KernelInterface $kernel, RewindableGenerator $generator): CommandTester
    {
        $application = new Application($kernel);
        $application->add(new CachePoolPruneCommand($generator));

        return new CommandTester($application->find('cache:pool:prune'));
    }
}
