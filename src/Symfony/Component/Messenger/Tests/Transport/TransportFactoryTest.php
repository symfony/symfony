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
        $serializer = $this->createMock(SerializerInterface::class);
        $factories = [];
        foreach ($transportSupport as $supported) {
            $factory = $this->createMock(TransportFactoryInterface::class);
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
        yield 'show dsn when no transport supports' => [
            [false],
            'foobar://barfoo',
            'No transport supports Messenger DSN "foobar://barfoo".',
        ];
        yield 'empty dsn' => [
            [false],
            '',
            'No transport supports the given Messenger DSN.',
        ];
        yield 'dsn with no scheme' => [
            [false],
            'barfoo@bar',
            'No transport supports Messenger DSN "barfoo@bar".',
        ];
        yield 'dsn with empty scheme ' => [
            [false],
            '://barfoo@bar',
            'No transport supports Messenger DSN "://barfoo@bar".',
        ];
        yield 'https dsn' => [
            [false],
            'https://sqs.foobar.amazonaws.com',
            'No transport supports Messenger DSN "https://sqs.foobar.amazonaws.com"',
        ];
        yield 'with package suggestion amqp://' => [
            [false],
            'amqp://foo:barfoo@bar',
            'No transport supports Messenger DSN "amqp://foo:******@bar". Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'replaces password with stars' => [
            [false],
            'amqp://myuser:mypassword@broker:5672/vhost',
            'No transport supports Messenger DSN "amqp://myuser:******@broker:5672/vhost". Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'username only is blanked out (as this could be a secret token)' => [
            [false],
            'amqp://myuser@broker:5672/vhost',
            'No transport supports Messenger DSN "amqp://******@broker:5672/vhost". Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
        yield 'empty password' => [
            [false],
            'amqp://myuser:@broker:5672/vhost',
            'No transport supports Messenger DSN "amqp://myuser:******@broker:5672/vhost". Run "composer require symfony/amqp-messenger" to install AMQP transport.',
        ];
    }
}
