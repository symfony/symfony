<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dsn\Configuration\Dsn;
use Symfony\Component\Dsn\Configuration\DsnFunction;
use Symfony\Component\Dsn\Configuration\Path;
use Symfony\Component\Dsn\Configuration\Url;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\FunctionsNotAllowedException;
use Symfony\Component\Dsn\Exception\SyntaxException;

class DsnParserTest extends TestCase
{
    public function validDsnProvider(): iterable
    {
        yield ['sqs://user:B3%26iX%5EiOCLN%2Ab@aws.com', new Url('sqs', 'aws.com', null, null, [], ['user' => 'user', 'password' => 'B3&iX^iOCLN*b'])];
        yield ['node:45', new Url(null, 'node', 45)];
        yield ['memcached://127.0.0.1/50', new Url('memcached', '127.0.0.1', null, '/50')];
        yield ['memcached://localhost:11222?weight=25', new Url('memcached', 'localhost', 11222, null, ['weight' => '25'])];
        yield ['memcached://user:password@127.0.0.1?weight=50', new Url('memcached', '127.0.0.1', null, null, ['weight' => '50'], ['user' => 'user', 'password' => 'password'])];
        yield ['memcached://:password@127.0.0.1?weight=50', new Url('memcached', '127.0.0.1', null, null, ['weight' => '50'], ['user' => null, 'password' => 'password'])];
        yield ['memcached://user@127.0.0.1?weight=50', new Url('memcached', '127.0.0.1', null, null, ['weight' => '50'], ['user' => 'user', 'password' => null])];
        yield ['memcached:///var/run/memcached.sock?weight=25', new Path('memcached', '/var/run/memcached.sock', ['weight' => '25'])];
        yield ['memcached://user:password@/var/local/run/memcached.socket?weight=25', new Path('memcached', '/var/local/run/memcached.socket', ['weight' => '25'], ['user' => 'user', 'password' => 'password'])];
        yield ['memcached://localhost?host[foo.bar]=3', new Url('memcached', 'localhost', null, null, ['host' => ['foo.bar' => '3']])];
        yield ['redis:?host[redis1]&host[redis2]&host[redis3]&redis_cluster=1&redis_sentinel=mymaster', new Dsn('redis', ['host' => ['redis1' => '', 'redis2' => '', 'redis3' => ''], 'redis_cluster' => '1', 'redis_sentinel' => 'mymaster'])];
        yield ['redis:?host[h1]&host[h2]&host[/foo:]', new Dsn('redis', ['host' => ['h1' => '', 'h2' => '', '/foo:' => '']])];
        yield ['rediss:?host[h1]&host[h2]&host[/foo:]', new Dsn('rediss', ['host' => ['h1' => '', 'h2' => '', '/foo:' => '']])];
        yield ['dummy://a', new Url('dummy', 'a')];
        yield ['failover(dummy://a dummy://b)', new DsnFunction('failover', [new Url('dummy', 'a'), new Url('dummy', 'b')])];
        yield ['failover(dummy://a, dummy://b)', new DsnFunction('failover', [new Url('dummy', 'a'), new Url('dummy', 'b')])];
        yield ['failover(dummy://a,dummy://b)', new DsnFunction('failover', [new Url('dummy', 'a'), new Url('dummy', 'b')])];
        yield ['roundrobin(dummy://a failover(dummy://b dummy://a) dummy://b)', new DsnFunction('roundrobin', [new Url('dummy', 'a'), new DsnFunction('failover', [new Url('dummy', 'b'), new Url('dummy', 'a')]), new Url('dummy', 'b')])];
        yield ['null://', new Dsn('null')];
        yield ['sync://', new Dsn('sync')];
        yield ['in-memory://', new Dsn('in-memory')];
        yield ['amqp://host/%2f/custom', new Url('amqp', 'host', null, '/%2f/custom')];

        yield ['amqp://localhost/%2f/messages?'.
            'queues[messages][arguments][x-dead-letter-exchange]=dead-exchange&'.
            'queues[messages][arguments][x-message-ttl]=100&'.
            'queues[messages][arguments][x-delay]=100&'.
            'queues[messages][arguments][x-expires]=150&',
            new Url('amqp', 'localhost', null, '/%2f/messages', [
                'queues' => [
                    'messages' => [
                        'arguments' => [
                            'x-dead-letter-exchange' => 'dead-exchange',
                            'x-message-ttl' => '100',
                            'x-delay' => '100',
                            'x-expires' => '150',
                        ],
                    ],
                ],
            ]),
            ];

        yield ['redis:///var/run/redis/redis.sock', new Path('redis', '/var/run/redis/redis.sock')];
        yield ['failover:(node1:123,node2:1234)', new DsnFunction('failover', [new Url(null, 'node1', 123), new Url(null, 'node2', 1234)])];
        yield ['mysql+replication:(mysql+master://master:3306,mysql+slave://slave:3306,slave2:3306)', new DsnFunction('mysql+replication', [new Url('mysql+master', 'master', 3306), new Url('mysql+slave', 'slave', 3306), new Url(null, 'slave2', 3306)])];
        yield ['mysql+replication:(mysql+master://master:3306 mysql+slave://slave:3306 slave2:3306)', new DsnFunction('mysql+replication', [new Url('mysql+master', 'master', 3306), new Url('mysql+slave', 'slave', 3306), new Url(null, 'slave2', 3306)])];
        yield ['failover:(amqp+ssl://node1.mq.eu-west-1.amazonaws.com:5671,amqp+ssl://node2.mq.eu-west-1.amazonaws.com:5671)', new DsnFunction('failover', [new Url('amqp+ssl', 'node1.mq.eu-west-1.amazonaws.com', 5671), new Url('amqp+ssl', 'node2.mq.eu-west-1.amazonaws.com', 5671)])];
        yield ['failover:(tcp://localhost:61616,tcp://remotehost:61616)?initialReconnectDelay=100', new DsnFunction('failover', [new Url('tcp', 'localhost', 61616), new Url('tcp', 'remotehost', 61616)], ['initialReconnectDelay' => '100'])];
        yield ['foo(udp://localhost failover:(tcp://localhost:61616,tcp://remotehost:61616)?initialReconnectDelay=100)?start=now', new DsnFunction('foo', [
            new Url('udp', 'localhost'),
            new DsnFunction('failover', [new Url('tcp', 'localhost', 61616), new Url('tcp', 'remotehost', 61616)], ['initialReconnectDelay' => '100']),
        ], ['start' => 'now'])];
    }

    public function fromWrongStringProvider(): iterable
    {
        yield 'garbage at the end' => ['dummy://a some garbage here'];
        yield 'not a valid DSN' => ['something not a dsn'];
        yield 'failover not closed' => ['failover(dummy://a'];
        yield ['(dummy://a)'];
        yield ['foo(dummy://a bar()'];
        yield [''];
        yield ['foo(dummy://a bar())'];
        yield ['foo()'];
        yield ['amqp://user:pass:word@localhost'];
        yield ['amqp://user:pass@word@localhost'];
        yield ['amqp://user:pass/word@localhost'];
        yield ['amqp://user:pass/word@localhost'];
        yield ['amqp://user@name:pass@localhost'];
        yield ['amqp://user/name:pass@localhost'];
    }

    /**
     * @dataProvider validDsnProvider
     */
    public function testParse(string $dsn, $expected)
    {
        if (!$expected instanceof DsnFunction) {
            $expected = new DsnFunction('dsn', [$expected]);
        }

        $result = DsnParser::parseFunc($dsn);
        $this->assertEquals($expected, $result);
    }

    public function testParseSimple()
    {
        $result = DsnParser::parse('amqp://user:pass@localhost:5672/%2f/messages');
        $this->assertEquals(new Url('amqp', 'localhost', 5672, '/%2f/messages', [], ['user' => 'user', 'password' => 'pass']), $result);

        $result = DsnParser::parse('dsn(amqp://localhost)');
        $this->assertEquals(new Url('amqp', 'localhost'), $result);
    }

    public function testParseSimpleWithFunction()
    {
        $this->expectException(FunctionsNotAllowedException::class);
        DsnParser::parse('foo(amqp://localhost)');
    }

    /**
     * @dataProvider fromWrongStringProvider
     */
    public function testParseInvalid(string $dsn)
    {
        $this->expectException(SyntaxException::class);
        DsnParser::parseFunc($dsn);
    }
}
