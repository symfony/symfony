<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\KernelTerminateTransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KernelTerminateTransportFactoryTest extends TestCase
{
    public function testItCreatesATransportWithBusInDsn()
    {
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $busLocator->method('has')->with('aBus')->willReturn(true);
        $busLocator->method('get')->with('aBus')->willReturn($messageBus);

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $transport = $kernelTerminateTransportFactory->createTransport('symfony://kernel.terminate?bus=aBus', array());

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testItCreatesATransportWithBusInOptions()
    {
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $busLocator->method('has')->with('aBus')->willReturn(true);
        $busLocator->method('get')->with('aBus')->willReturn($messageBus);

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $transport = $kernelTerminateTransportFactory->createTransport('symfony://kernel.terminate', array('bus' => 'aBus'));

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    /**
     * @dataProvider dsnProvider
     */
    public function testItSupportsKernelTerminateTransport(string $dsn, array $options, $expected)
    {
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $supports = $kernelTerminateTransportFactory->supports($dsn, $options);

        $this->assertEquals($expected, $supports);
    }

    public function dsnProvider()
    {
        yield array('symfony://kernel.terminate?x=y', array(), true);
        yield array('symfony://kernel.terminate', array(), true);
        yield array('aSymfony://kernel.terminate', array(), false);
        yield array('symfony://kernel.exception', array(), false);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\InvalidArgumentException
     * @expectedExceptionMessage The given kernel.terminate DSN "http://" is invalid.
     */
    public function testItThrowsExceptionIfIncorrectDsnFormat()
    {
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $kernelTerminateTransportFactory->createTransport('http://', array());
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing mandatory "bus" option for kernel.terminate transport with DSN "symfony://kernel.terminate"
     */
    public function testItThrowsExceptionIfNoBusProvided()
    {
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $kernelTerminateTransportFactory->createTransport('symfony://kernel.terminate', array());
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\InvalidArgumentException
     * @expectedExceptionMessage No bus was found with id "aBus" for kernel.terminate transport with DSN "symfony://kernel.terminate"
     */
    public function testItThrowsExceptionIfBusIsNotFound()
    {
        $busLocator = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $busLocator->method('has')->with('aBus')->willReturn(false);

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $kernelTerminateTransportFactory = new KernelTerminateTransportFactory($busLocator, $eventDispatcher);
        $kernelTerminateTransportFactory->createTransport('symfony://kernel.terminate', array('bus' => 'aBus'));
    }
}
