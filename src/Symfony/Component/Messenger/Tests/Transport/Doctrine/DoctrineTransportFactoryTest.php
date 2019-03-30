<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Doctrine;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory;

class DoctrineTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new DoctrineTransportFactory(
            $this->getMockBuilder(RegistryInterface::class)->getMock(),
            null,
            false
        );

        $this->assertTrue($factory->supports('doctrine://default', []));
        $this->assertFalse($factory->supports('amqp://localhost', []));
    }

    public function testCreateTransport()
    {
        $connection = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $registry = $this->getMockBuilder(RegistryInterface::class)->getMock();
        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);

        $factory = new DoctrineTransportFactory(
            $registry,
            null
        );

        $this->assertEquals(
            new DoctrineTransport(new Connection(Connection::buildConfiguration('doctrine://default'), $connection), null),
            $factory->createTransport('doctrine://default', [])
        );
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\TransportException
     * @expectedExceptionMessage Could not find Doctrine connection from DSN "doctrine://default".
     */
    public function testCreateTransportMustThrowAnExceptionIfManagerIsNotFound()
    {
        $registry = $this->getMockBuilder(RegistryInterface::class)->getMock();
        $registry->expects($this->once())
            ->method('getConnection')
            ->will($this->returnCallback(function () {
                throw new \InvalidArgumentException();
            }));

        $factory = new DoctrineTransportFactory(
            $registry,
            null
        );

        $factory->createTransport('doctrine://default', []);
    }
}
