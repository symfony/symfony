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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Dsn;

class DsnTest extends TestCase
{
    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(string $string, Dsn $dsn, array $options = []): void
    {
        $this->assertEquals($dsn, Dsn::fromString($string, $options));
    }

    public function fromStringProvider(): iterable
    {
        yield 'amqp' => [
            'amqp://',
            new Dsn('amqp'),
        ];

        yield 'amqp with host' => [
            'amqp://localhost',
            new Dsn('amqp', 'localhost'),
        ];

        yield 'amqp with host, user and pass' => [
            'amqp://guest:pass@localhost',
            new Dsn('amqp', 'localhost', 'guest', 'pass'),
        ];

        yield 'redis with host and port' => [
            'redis://localhost:6379',
            new Dsn('redis', 'localhost', null, null, 6379),
        ];

        yield 'redis with host and option inside dsn' => [
            'redis://localhost?stream=messages',
            new Dsn('redis', 'localhost', null, null, null, null, ['stream' => 'messages']),
        ];

        yield 'redis with host and option inside options argument' => [
            'redis://localhost',
            new Dsn('redis', 'localhost', null, null, null, null, ['stream' => 'messages', 'auto_setup' => false]),
            ['stream' => 'messages', 'auto_setup' => false],
        ];

        yield 'redis with host and same option inside dsn and options argument' => [
            'redis://localhost?stream=stream_from_dsn',
            new Dsn('redis', 'localhost', null, null, null, null, ['stream' => 'stream_from_dsn']),
            ['stream' => 'stream_from_options'],
        ];
    }

    /**
     * @dataProvider toStringProvider
     */
    public function testToString(Dsn $dsn, string $string): void
    {
        $this->assertSame($string, (string) $dsn);
    }

    public function toStringProvider(): iterable
    {
        yield 'amqp' => [
            new Dsn('amqp'),
            'amqp://',
        ];

        yield 'amqp with host' => [
            new Dsn('amqp', 'localhost'),
            'amqp://localhost',
        ];

        yield 'amqp with host, user and pass' => [
            new Dsn('amqp', 'localhost', 'guest', 'pass'),
            'amqp://guest:pass@localhost',
        ];

        yield 'redis with host and port' => [
            new Dsn('redis', 'localhost', null, null, 6379),
            'redis://localhost:6379',
        ];

        yield 'redis with host and options' => [
            new Dsn('redis', 'localhost', null, null, null, null, ['stream' => 'messages', 'auto_setup' => false]),
            'redis://localhost?stream=messages&auto_setup=0',
        ];
    }

    /**
     * @dataProvider invalidDsnProvider
     */
    public function testInvalidDsn(string $dsn, string $exceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);
        Dsn::fromString($dsn);
    }

    public function invalidDsnProvider(): iterable
    {
        yield [
            'foo',
            'The "foo" messenger DSN is invalid.',
        ];
    }
}
