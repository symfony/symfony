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
        foreach (['amp-sqlite:///var/messenger.db', 'amp-mysql://localhost/app', 'amp-postgres://localhost/app'] as $dsn) {
            yield 'with package suggestion '.$dsn => [
                [false],
                $dsn,
                str_contains($dsn, '@') || !str_starts_with($dsn, 'amp-sqlite:')
                    ? \sprintf('No transport supports Messenger DSN "%s". Run "composer require symfony/amp-sql-messenger" to install AMPHP SQL transport.', $dsn)
                    : 'No transport supports the given Messenger DSN. Run "composer require symfony/amp-sql-messenger" to install AMPHP SQL transport.',
            ];
        }
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
