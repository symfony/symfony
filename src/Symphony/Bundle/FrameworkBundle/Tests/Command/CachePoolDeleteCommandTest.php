<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symphony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand;
use Symphony\Bundle\FrameworkBundle\Console\Application;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Component\Console\Tester\CommandTester;
use Symphony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symphony\Component\HttpKernel\KernelInterface;

class CachePoolDeleteCommandTest extends TestCase
{
    private $cachePool;

    protected function setUp()
    {
        $this->cachePool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->getMock();
    }

    public function testCommandWithValidKey()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(true);

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(array('pool' => 'foo', 'key' => 'bar'));

        $this->assertContains('[OK] Cache item "bar" was successfully deleted.', $tester->getDisplay());
    }

    public function testCommandWithInValidKey()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(false);

        $this->cachePool->expects($this->never())
            ->method('deleteItem')
            ->with('bar');

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(array('pool' => 'foo', 'key' => 'bar'));

        $this->assertContains('[NOTE] Cache item "bar" does not exist in cache pool "foo".', $tester->getDisplay());
    }

    public function testCommandDeleteFailed()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(false);

        if (method_exists($this, 'expectExceptionMessage')) {
            $this->expectExceptionMessage('Cache item "bar" could not be deleted.');
        } else {
            $this->setExpectedException('Exception', 'Cache item "bar" could not be deleted.');
        }

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(array('pool' => 'foo', 'key' => 'bar'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|KernelInterface
     */
    private function getKernel()
    {
        $container = $this
            ->getMockBuilder('Symphony\Component\DependencyInjection\ContainerInterface')
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
            ->willReturn(array());

        return $kernel;
    }

    private function getCommandTester(KernelInterface $kernel): CommandTester
    {
        $application = new Application($kernel);
        $application->add(new CachePoolDeleteCommand(new Psr6CacheClearer(array('foo' => $this->cachePool))));

        return new CommandTester($application->find('cache:pool:delete'));
    }
}
