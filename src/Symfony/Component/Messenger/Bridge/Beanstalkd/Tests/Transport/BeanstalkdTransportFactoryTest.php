<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransport;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class BeanstalkdTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new BeanstalkdTransportFactory();

        $this->assertTrue($factory->supports('beanstalkd://127.0.0.1', []));
        $this->assertFalse($factory->supports('doctrine://127.0.0.1', []));
    }

    public function testCreateTransport()
    {
        $factory = new BeanstalkdTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);

        $this->assertEquals(
            new BeanstalkdTransport(Connection::fromDsn('beanstalkd://127.0.0.1'), $serializer),
            $factory->createTransport('beanstalkd://127.0.0.1', [], $serializer)
        );
    }
}
