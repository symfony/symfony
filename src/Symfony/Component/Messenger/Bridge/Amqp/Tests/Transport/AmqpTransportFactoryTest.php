<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmqpTransportFactoryTest extends TestCase
{
    public function testSupportsOnlyAmqpTransports()
    {
        $factory = new AmqpTransportFactory();

        $this->assertTrue($factory->supports('amqp://localhost', []));
        $this->assertFalse($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    #[RequiresPhpExtension('amqp')]
    public function testItCreatesTheTransport()
    {
        $factory = new AmqpTransportFactory();
        $serializer = $this->createStub(SerializerInterface::class);

        $expectedTransport = new AmqpTransport(Connection::fromDsn('amqp://localhost', ['host' => 'localhost']), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('amqp://localhost', ['host' => 'localhost'], $serializer));
    }

    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $factory = new AmqpTransportFactory();

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $factory->createTransport('amqp://localhost', ['password' => 's3cr3t', 'foo' => 'bar'], $this->createStub(SerializerInterface::class));
            $this->fail('An exception should have been thrown.');
        } catch (\LogicException $e) {
            $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
