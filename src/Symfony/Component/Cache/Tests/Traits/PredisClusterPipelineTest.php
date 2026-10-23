<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\Command\Processor\ProcessorInterface;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Connection\Cluster\RedisCluster as Predis2RedisCluster;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use Predis\Response\Error;
use Predis\Response\Status;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Traits\RedisTrait;

class PredisClusterPipelineTest extends TestCase
{
    public function testRunsSingleCommandsDirectly()
    {
        $node = $this->createMock(NodeConnectionInterface::class);
        $node->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->once())->method('executeCommand')->willReturn('value');
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', 'value']], $adapter->run(static function () {
            yield 'get' => ['a'];
        }));
    }

    public function testPipelinesReadsByNodeAndPreservesResultOrder()
    {
        $events = [];
        $first = $this->createNode($events, 'first', static fn ($command) => $command->getArgument(0));
        $second = $this->createNode($events, 'second', static fn ($command) => $command->getArgument(0));
        $connection = $this->createCluster(static fn ($command) => 'prefix_b' === $command->getArgument(0) ? $second : $first);
        $connection->expects($this->never())->method('executeCommand');
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false, 'prefix' => 'prefix_']));

        $results = $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
            yield 'get' => ['c'];
        });

        $this->assertSame([['a', 'prefix_a'], ['b', 'prefix_b'], ['c', 'prefix_c']], $results);
        $this->assertSame([
            ['first', 'write', 'GET', ['prefix_a']],
            ['first', 'write', 'GET', ['prefix_c']],
            ['first', 'read', 'GET', ['prefix_a']],
            ['first', 'read', 'GET', ['prefix_c']],
            ['second', 'write', 'GET', ['prefix_b']],
            ['second', 'read', 'GET', ['prefix_b']],
        ], $events);
    }

    #[DataProvider('provideClusterErrors')]
    public function testDrainsEveryNodeBeforeRetryingClusterErrors(string $redirection)
    {
        $events = [];
        $first = $this->createNode($events, 'first', static fn () => new Error($redirection.' 1234 127.0.0.1:7001'));
        $second = $this->createNode($events, 'second', static fn () => 'second value');
        $connection = $this->createCluster(static fn ($command) => 'a' === $command->getArgument(0) ? $first : $second);
        $connection->expects($this->once())->method('executeCommand')->willReturnCallback(static function ($command) use (&$events) {
            $events[] = ['retry', $command->getArgument(0)];

            return 'first value';
        });
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', 'first value'], ['b', 'second value']], $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
        }));
        $this->assertSame(['second', 'read', 'GET', ['b']], $events[3]);
        $this->assertSame(['retry', 'a'], $events[4]);
    }

    public static function provideClusterErrors(): iterable
    {
        yield ['MOVED'];
        yield ['ASK'];
        yield ['CLUSTERDOWN'];
    }

    public function testDoesNotRetryServerErrors()
    {
        $events = [];
        $error = new Error('OOM command not allowed');
        $node = $this->createNode($events, 'node', static fn () => $error);
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->never())->method('executeCommand');
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', $error], ['b', $error]], $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
        }));
    }

    public function testDoesNotRetryAfterClusterRecoveryIsExhausted()
    {
        $events = [];
        $node = $this->createNode($events, 'node', static fn () => new Error('MOVED 1234 127.0.0.1:7001'));
        $connection = $this->createCluster(static fn () => $node);
        $error = new ConnectionException($node, 'Retries exhausted');
        $connection->expects($this->once())->method('executeCommand')->willThrowException($error);
        $connection->expects($this->never())->method('disconnect');
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->expectExceptionObject($error);

        $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
        });
    }

    public function testRunsRepeatedKeysSequentially()
    {
        $events = [];
        $value = null;
        $node = $this->createMock(NodeConnectionInterface::class);
        $node->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->exactly(2))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events, &$value) {
            $events[] = $command->getArguments();
            $value = $command->getArgument(1);

            return Status::get('OK');
        });
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', Status::get('OK')], ['a', Status::get('OK')]], $adapter->run(static function () {
            yield 'set' => ['a', 'first'];
            yield 'set' => ['a', 'second'];
        }));
        $this->assertSame([['a', 'first'], ['a', 'second']], $events);
        $this->assertSame('second', $value);
    }

    public function testRunsScriptsSequentially()
    {
        $events = [];
        $node = $this->createMock(NodeConnectionInterface::class);
        $node->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->exactly(3))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events) {
            $events[] = [$command->getId(), $command->getArguments()];

            return 'EVAL' === $command->getId() ? 1 : Status::get('OK');
        });
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));
        $script = "return redis.call('DEL', ARGV[1])";

        $this->assertSame([['a', Status::get('OK')], ['tag', 1], ['b', Status::get('OK')]], $adapter->run(static function () use ($script) {
            yield 'set' => ['a', 'value'];
            yield 'eval' => [$script, 1, 'tag', 'b'];
            yield 'set' => ['b', 'value'];
        }));
        $this->assertSame([
            ['SET', ['a', 'value']],
            ['EVAL', [$script, 1, 'tag', 'b']],
            ['SET', ['b', 'value']],
        ], $events);
    }

    public function testRunsKeysMergedByCommandProcessorSequentially()
    {
        $events = [];
        $node = $this->createMock(NodeConnectionInterface::class);
        $node->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->exactly(2))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events) {
            $events[] = $command->getArguments();

            return 'value';
        });
        $processor = new class implements ProcessorInterface {
            public function process(CommandInterface $command)
            {
                $arguments = $command->getArguments();
                $arguments[0] = 'same';
                $command->setRawArguments($arguments);
            }
        };
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false, 'prefix' => $processor]));

        $this->assertSame([['a', 'value'], ['b', 'value']], $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
        }));
        $this->assertSame([['same'], ['same']], $events);
    }

    public function testDisconnectsAndRetriesReadsBeforeReusingConnections()
    {
        $events = [];
        $pending = [];
        $fail = true;
        $node = $this->createStub(NodeConnectionInterface::class);
        $node->method('writeRequest')->willReturnCallback(static function ($command) use (&$pending) {
            $pending[] = $command->getArgument(0);
        });
        $node->method('readResponse')->willReturnCallback(static function () use ($node, &$pending, &$fail) {
            if ($fail) {
                $fail = false;
                throw new ConnectionException($node, 'Read failed');
            }

            return array_shift($pending);
        });
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->never())->method('readResponse');
        $connection->expects($this->exactly(3))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events) {
            $events[] = ['retry', $command->getArgument(0)];

            return $command->getArgument(0);
        });
        $connection->expects($this->once())->method('disconnect')->willReturnCallback(static function () use (&$events, &$pending) {
            $events[] = ['disconnect'];
            $pending = [];
        });
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', 'a'], ['b', 'b']], $adapter->run(static function () {
            yield 'get' => ['a'];
            yield 'get' => ['b'];
        }));
        $this->assertSame([], $pending);

        $this->assertSame([['c', 'c']], $adapter->run(static function () {
            yield 'get' => ['c'];
        }));
        $this->assertSame([['disconnect'], ['retry', 'a'], ['retry', 'b'], ['retry', 'c']], $events);
    }

    public function testSavePreservesBinaryPayloadsAndReportsFailedKeys()
    {
        $events = [];
        $payloads = [];
        $node = $this->createMock(NodeConnectionInterface::class);
        $node->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn () => $node);
        $connection->expects($this->exactly(2))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events, &$payloads) {
            $events[] = [$command->getId(), $command->getArguments()];
            $payloads[$command->getArgument(0)] = $command->getArgument(2);

            return 'prefix_bad' === $command->getArgument(0) ? new Error('OOM command not allowed') : Status::get('OK');
        });
        $adapter = new class(new Client($connection, ['exceptions' => false, 'prefix' => 'prefix_']), '', 0, new DefaultMarshaller(false)) extends RedisAdapter {
            public function saveValues(array $values): array|bool
            {
                return $this->doSave($values, 60);
            }
        };

        $this->assertSame(['bad'], $adapter->saveValues(['good' => "\0\xFF\r\n", 'bad' => 'value']));
        $this->assertSame("\0\xFF\r\n", unserialize($payloads['prefix_good']));
        $this->assertSame(['SETEX', ['prefix_good', 60, $payloads['prefix_good']]], $events[0]);
    }

    public function testStoresItemsBeforeAddingTagRelations()
    {
        $events = [];
        $first = $this->createMock(NodeConnectionInterface::class);
        $first->expects($this->never())->method('writeRequest');
        $second = $this->createMock(NodeConnectionInterface::class);
        $second->expects($this->never())->method('writeRequest');
        $connection = $this->createCluster(static fn ($command) => 'b' === $command->getArgument(0) ? $second : $first);
        $connection->expects($this->exactly(3))->method('executeCommand')->willReturnCallback(static function ($command) use (&$events) {
            $events[] = [$command->getId(), $command->getArguments()];

            return 'SADD' === $command->getId() ? 1 : Status::get('OK');
        });
        $adapter = $this->createAdapter(new Client($connection, ['exceptions' => false]));

        $this->assertSame([['a', Status::get('OK')], ['b', Status::get('OK')], ['tag', 1]], $adapter->run(static function () {
            yield 'setEx' => ['a', 60, 'value'];
            yield 'setEx' => ['b', 60, 'value'];
            yield 'sAdd' => ['tag', 'a', 'b'];
        }));
        $this->assertSame([
            ['SETEX', ['a', 60, 'value']],
            ['SETEX', ['b', 60, 'value']],
            ['SADD', ['tag', 'a', 'b']],
        ], $events);
    }

    private function createNode(array &$events, string $name, \Closure $response): NodeConnectionInterface
    {
        $node = $this->createStub(NodeConnectionInterface::class);
        $node->method('writeRequest')->willReturnCallback(static function ($command) use (&$events, $name) {
            $events[] = [$name, 'write', $command->getId(), $command->getArguments()];
        });
        $node->method('readResponse')->willReturnCallback(static function ($command) use (&$events, $name, $response) {
            $events[] = [$name, 'read', $command->getId(), $command->getArguments()];

            return $response($command);
        });

        return $node;
    }

    private function createCluster(\Closure $route): RedisCluster|Predis2RedisCluster
    {
        $connection = $this->createMock(class_exists(Predis2RedisCluster::class) ? Predis2RedisCluster::class : RedisCluster::class);
        $connection->method('getConnectionByCommand')->willReturnCallback($route);

        return $connection;
    }

    private function createAdapter(Client $redis): object
    {
        return new class($redis) {
            use RedisTrait;

            public function __construct(Client $redis)
            {
                $this->redis = $redis;
            }

            public function run(\Closure $commands): array
            {
                $results = [];
                foreach ($this->pipeline($commands) as $id => $result) {
                    $results[] = [$id, $result];
                }

                return $results;
            }
        };
    }
}
