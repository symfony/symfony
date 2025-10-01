<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

#[RequiresPhpExtension('redis')]
class RedisTransportFactoryTest extends TestCase
{
    use VarDumperTestTrait;

    public function testSupportsOnlyRedisTransports()
    {
        $factory = new RedisTransportFactory();

        $this->assertTrue($factory->supports('redis://localhost', []));
        $this->assertTrue($factory->supports('rediss://localhost', []));
        $this->assertTrue($factory->supports('valkey://localhost', []));
        $this->assertTrue($factory->supports('valkeys://localhost', []));
        $this->assertTrue($factory->supports('redis:?host[host1:5000]&host[host2:5000]&host[host3:5000]&sentinel=test&dbindex=0', []));
        $this->assertFalse($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    #[DataProvider('createTransportProvider')]
    #[Group('integration')]
    public function testCreateTransport(string $dsn, array $options = [])
    {
        $this->skipIfRedisUnavailable();

        $factory = new RedisTransportFactory();
        $serializer = new PhpSerializer();

        $this->assertDumpEquals(
            new RedisTransport(Connection::fromDsn($dsn, $options), $serializer),
            $factory->createTransport($dsn, $options, $serializer)
        );
    }

    /**
     * @return iterable<array{0: string, 1: array}>
     */
    public static function createTransportProvider(): iterable
    {
        yield 'scheme "redis" without options' => [
            'redis://'.getenv('REDIS_HOST'),
            [],
        ];

        yield 'scheme "redis" with options' => [
            'redis://'.getenv('REDIS_HOST'),
            ['stream' => 'bar', 'delete_after_ack' => true],
        ];

        if (false !== getenv('REDIS_SENTINEL_HOSTS') && false !== getenv('REDIS_SENTINEL_SERVICE')) {
            yield 'redis_sentinel' => [
                'redis:?host['.str_replace(' ', ']&host[', getenv('REDIS_SENTINEL_HOSTS')).']',
                ['sentinel' => getenv('REDIS_SENTINEL_SERVICE')],
            ];
        }
    }

    private function skipIfRedisUnavailable()
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }
}
