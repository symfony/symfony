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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class TransportFactoryTest extends TestCase
{
    #[DataProvider('provideThrowsExceptionOnUnsupportedTransport')]
    public function testThrowsExceptionOnUnsupportedTransport(array $transportSupport, string $dsn, ?string $expectedMessage)
    {
        if (null !== $expectedMessage) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedMessage);
        }
        $serializer = $this->createStub(SerializerInterface::class);
        $factories = [];
        foreach ($transportSupport as $supported) {
            $factory = $this->createStub(TransportFactoryInterface::class);
            $factory->method('supports', $dsn, [])->willReturn($supported);
            $factories[] = $factory;
        }

        $factory = new TransportFactory($factories);
        $transport = $factory->createTransport($dsn, [], $serializer);

        if (null !== $expectedMessage) {
            return;
        }

        self::assertInstanceOf(TransportInterface::class, $transport);
    }

    public static function provideThrowsExceptionOnUnsupportedTransport(): \Generator
    {
        yield 'transport supports dsn' => [
            [true],
            'foobar://barfoo',
            null,
        ];
        yield 'no transport supports dsn' => [
            [false],
            'foobar://barfoo',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'empty dsn' => [
            [false],
            '',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'dsn with no scheme' => [
            [false],
            'barfoo@bar',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'dsn with empty scheme ' => [
            [false],
            '://barfoo@bar',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'https dsn' => [
            [false],
            'https://sqs.foobar.amazonaws.com',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'with package suggestion amqp://' => [
            [false],
            'amqp://foo:barfoo@bar',
            'No transport supports the given Messenger DSN. Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'password' => [
            [false],
            'amqp://myuser:mypassword@broker:5672/vhost',
            'No transport supports the given Messenger DSN. Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'username only' => [
            [false],
            'amqp://myuser@broker:5672/vhost',
            'No transport supports the given Messenger DSN. Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'empty password' => [
            [false],
            'amqp://myuser:@broker:5672/vhost',
            'No transport supports the given Messenger DSN. Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'password with a slash' => [
            [false],
            'amqp://myuser:12/34@broker:5672/vhost',
            'No transport supports the given Messenger DSN. Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
    }

    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $factory = new TransportFactory([]);

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $factory->createTransport('foo://localhost', ['password' => 's3cr3t'], $this->createStub(SerializerInterface::class));
            $this->fail('An exception should have been thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
