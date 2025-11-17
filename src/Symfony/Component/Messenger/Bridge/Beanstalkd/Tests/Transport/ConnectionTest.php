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

use Pheanstalk\Contract\PheanstalkManagerInterface;
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Exception;
use Pheanstalk\Exception\ClientException;
use Pheanstalk\Exception\ConnectionException;
use Pheanstalk\Exception\DeadlineSoonException;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\JobState;
use Pheanstalk\Values\JobStats;
use Pheanstalk\Values\TubeList;
use Pheanstalk\Values\TubeName;
use Pheanstalk\Values\TubeStats;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Exception\InvalidArgumentException as MessengerInvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

final class ConnectionTest extends TestCase
{
    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Beanstalkd DSN is invalid.');

        Connection::fromDsn('beanstalkd://');
    }

    public function testFromDsn()
    {
        $this->assertEquals(
            $connection = new Connection([], Pheanstalk::create('127.0.0.1', 11300)),
            Connection::fromDsn('beanstalkd://127.0.0.1')
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame('default', $configuration['tube_name']);
        $this->assertSame(0, $configuration['timeout']);
        $this->assertSame(90, $configuration['ttr']);
        $this->assertFalse($configuration['bury_on_reject']);

        $this->assertEquals(
            $connection = new Connection([], Pheanstalk::create('foobar', 15555)),
            Connection::fromDsn('beanstalkd://foobar:15555')
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame('default', $configuration['tube_name']);
        $this->assertSame(0, $configuration['timeout']);
        $this->assertSame(90, $configuration['ttr']);
        $this->assertFalse($configuration['bury_on_reject']);
        $this->assertSame('default', $connection->getTube());
    }

    public function testFromDsnWithOptions()
    {
        $this->assertEquals(
            $connectionWithOptions = Connection::fromDsn('beanstalkd://localhost', ['tube_name' => 'foo', 'timeout' => 10, 'ttr' => 5000, 'bury_on_reject' => true]),
            $connectionWithQuery = Connection::fromDsn('beanstalkd://localhost?tube_name=foo&timeout=10&ttr=5000&bury_on_reject=true')
        );

        $configuration = $connectionWithOptions->getConfiguration();

        $this->assertSame('foo', $configuration['tube_name']);
        $this->assertSame(10, $configuration['timeout']);
        $this->assertSame(5000, $configuration['ttr']);
        $this->assertTrue($configuration['bury_on_reject']);
        $this->assertSame('foo', $connectionWithOptions->getTube());

        $configuration = $connectionWithQuery->getConfiguration();

        $this->assertSame('foo', $configuration['tube_name']);
        $this->assertSame(10, $configuration['timeout']);
        $this->assertSame(5000, $configuration['ttr']);
        $this->assertTrue($configuration['bury_on_reject']);
        $this->assertSame('foo', $connectionWithOptions->getTube());
    }

    public function testFromDsnOptionsArrayWinsOverOptionsFromDsn()
    {
        $options = [
            'tube_name' => 'bar',
            'timeout' => 20,
            'ttr' => 6000,
            'bury_on_reject' => false,
        ];

        $this->assertEquals(
            $connection = new Connection($options, Pheanstalk::create('localhost', 11333)),
            Connection::fromDsn('beanstalkd://localhost:11333?tube_name=foo&timeout=10&ttr=5000&bury_on_reject=true', $options)
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame($options['tube_name'], $configuration['tube_name']);
        $this->assertSame($options['timeout'], $configuration['timeout']);
        $this->assertSame($options['ttr'], $configuration['ttr']);
        $this->assertSame($options['bury_on_reject'], $configuration['bury_on_reject']);
        $this->assertSame($options['tube_name'], $connection->getTube());
    }

    public function testItThrowsAnExceptionIfAnExtraOptionIsDefined()
    {
        $this->expectException(MessengerInvalidArgumentException::class);
        Connection::fromDsn('beanstalkd://127.0.0.1', ['new_option' => 'woops']);
    }

    public function testItThrowsAnExceptionIfAnExtraOptionIsDefinedInDSN()
    {
        $this->expectException(MessengerInvalidArgumentException::class);
        Connection::fromDsn('beanstalkd://127.0.0.1?new_option=woops');
    }

    public function testGet()
    {
        $id = '1234';
        $id2 = '1235';
        $beanstalkdEnvelope = [
            'body' => 'foo',
            'headers' => 'bar',
        ];

        $tube = 'baz';
        $timeout = 44;

        $tubeList = new TubeList($tubeName = new TubeName($tube), $tubeNameDefault = new TubeName('default'));

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('watch')->with($tubeName)->willReturn(2);
        $client->expects($this->once())->method('listTubesWatched')->willReturn($tubeList);
        $client->expects($this->once())->method('ignore')->with($tubeNameDefault)->willReturn(1);
        $client->expects($this->exactly(2))->method('reserveWithTimeout')->with($timeout)->willReturnOnConsecutiveCalls(
            new Job(new JobId($id), json_encode($beanstalkdEnvelope)),
            new Job(new JobId($id2), json_encode($beanstalkdEnvelope)),
        );

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $envelope = $connection->get();

        $this->assertSame($id, $envelope['id']);
        $this->assertSame($beanstalkdEnvelope['body'], $envelope['body']);
        $this->assertSame($beanstalkdEnvelope['headers'], $envelope['headers']);

        $envelope = $connection->get();

        $this->assertSame($id2, $envelope['id']);
        $this->assertSame($beanstalkdEnvelope['body'], $envelope['body']);
        $this->assertSame($beanstalkdEnvelope['headers'], $envelope['headers']);
    }

    public function testGetOnReconnect()
    {
        $id = '1234';
        $beanstalkdEnvelope = [
            'body' => 'foo',
            'headers' => 'bar',
        ];

        $tube = 'baz';
        $timeout = 44;

        $tubeList = new TubeList($tubeName = new TubeName($tube), $tubeNameDefault = new TubeName('default'));

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->exactly(2))->method('watch')->with($tubeName)->willReturn(2);
        $client->expects($this->exactly(2))->method('listTubesWatched')->willReturn($tubeList);
        $client->expects($this->exactly(2))->method('ignore')->with($tubeNameDefault)->willReturn(1);
        $client->expects($this->exactly(2))->method('reserveWithTimeout')->with($timeout)->willReturnOnConsecutiveCalls(
            $this->throwException(new ConnectionException('123', 'foobar')),
            new Job(new JobId($id), json_encode($beanstalkdEnvelope)),
        );

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $envelope = $connection->get();

        $this->assertSame($id, $envelope['id']);
        $this->assertSame($beanstalkdEnvelope['body'], $envelope['body']);
        $this->assertSame($beanstalkdEnvelope['headers'], $envelope['headers']);
    }

    public function testGetWhenThereIsNoJobInTheTube()
    {
        $tube = 'baz';
        $timeout = 44;

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('watch')->with(new TubeName($tube))->willReturn(1);
        $client->expects($this->never())->method('listTubesWatched');
        $client->expects($this->never())->method('ignore');
        $client->expects($this->once())->method('reserveWithTimeout')->with($timeout)->willReturn(null);

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $this->assertNull($connection->get());
    }

    public function testGetWhenABeanstalkdExceptionOccurs()
    {
        $tube = 'baz';
        $timeout = 44;

        $exception = new DeadlineSoonException('foo error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('watch')->with(new TubeName($tube))->willReturn(1);
        $client->expects($this->never())->method('listTubesWatched');
        $client->expects($this->never())->method('ignore');
        $client->expects($this->once())->method('reserveWithTimeout')->with($timeout)->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->get();
    }

    public function testAck()
    {
        $id = '123456';

        $tube = 'xyz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id));

        $connection = new Connection(['tube_name' => $tube], $client);

        $connection->ack($id);
    }

    public function testAckWhenABeanstalkdExceptionOccurs()
    {
        $id = '123456';

        $tube = 'xyzw';

        $exception = new ServerException('baz error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->ack($id);
    }

    #[TestWith([false, false])]
    #[TestWith([false, true])]
    #[TestWith([true, true])]
    public function testReject(bool $buryOnReject, bool $forceDelete)
    {
        $id = '123456';

        $tube = 'baz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id));

        $connection = new Connection(['tube_name' => $tube, 'bury_on_reject' => $buryOnReject], $client);

        $connection->reject($id, null, $forceDelete);
    }

    public function testRejectWithBury()
    {
        $id = '123456';

        $tube = 'baz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('bury')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id), 1024);

        $connection = new Connection(['tube_name' => $tube, 'bury_on_reject' => true], $client);

        $connection->reject($id);
    }

    public function testRejectWithBuryAndPriority()
    {
        $id = '123456';
        $priority = 2;

        $tube = 'baz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('bury')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id), $priority);

        $connection = new Connection(['tube_name' => $tube, 'bury_on_reject' => true], $client);

        $connection->reject($id, $priority);
    }

    public function testRejectWhenABeanstalkdExceptionOccurs()
    {
        $id = '123456';

        $tube = 'baz123';

        $exception = new ServerException('baz error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->reject($id);
    }

    public function testMessageCount()
    {
        $tube = 'baz';

        $count = 51;

        $response = new TubeStats($tubeName = new TubeName($tube), 0, 51, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tubeName);
        $client->expects($this->once())->method('statsTube')->with($tubeName)->willReturn($response);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->assertSame($count, $connection->getMessageCount());
    }

    public function testMessageCountWhenABeanstalkdExceptionOccurs()
    {
        $tube = 'baz1234';

        $exception = new ClientException('foobar error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('statsTube')->with(new TubeName($tube))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->getMessageCount();
    }

    public function testMessagePriority()
    {
        $id = '123456';
        $priority = 51;

        $tube = 'baz';

        $response = new JobStats(new JobId($id), new TubeName($tube), JobState::READY, $priority, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('statsJob')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willReturn($response);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->assertSame($priority, $connection->getMessagePriority($id));
    }

    public function testMessagePriorityWhenABeanstalkdExceptionOccurs()
    {
        $id = '123456';

        $tube = 'baz1234';

        $exception = new ClientException('foobar error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('statsJob')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->getMessagePriority($id);
    }

    public function testSend()
    {
        $tube = 'xyz';

        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 1000;
        $expectedDelay = $delay / 1000;

        $id = '110';
        $id2 = '111';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->exactly(2))->method('put')->with(
            $this->callback(function (string $data) use ($body, $headers): bool {
                $expectedMessage = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                ]);

                return $expectedMessage === $data;
            }),
            1024,
            $expectedDelay,
            90
        )->willReturnOnConsecutiveCalls(
            new Job(new JobId($id), 'foobar'),
            new Job(new JobId($id2), 'foobar'),
        );

        $connection = new Connection(['tube_name' => $tube], $client);

        $returnedId = $connection->send($body, $headers, $delay);

        $this->assertSame($id, $returnedId);

        $returnedId = $connection->send($body, $headers, $delay);

        $this->assertSame($id2, $returnedId);
    }

    public function testSendOnReconnect()
    {
        $tube = 'xyz';

        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 1000;
        $expectedDelay = $delay / 1000;

        $id = '110';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->exactly(2))->method('useTube')->with(new TubeName($tube));
        $client->expects($this->exactly(2))->method('put')->with(
            $this->callback(function (string $data) use ($body, $headers): bool {
                $expectedMessage = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                ]);

                return $expectedMessage === $data;
            }),
            1024,
            $expectedDelay,
            90
        )->willReturnOnConsecutiveCalls(
            $this->throwException(new ConnectionException('123', 'foobar')),
            new Job(new JobId($id), 'foobar'),
        );

        $connection = new Connection(['tube_name' => $tube], $client);

        $returnedId = $connection->send($body, $headers, $delay);

        $this->assertSame($id, $returnedId);
    }

    public function testSendWithPriority()
    {
        $tube = 'xyz';

        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 1000;
        $priority = 2;
        $expectedDelay = $delay / 1000;

        $id = '110';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('put')->with(
            $this->callback(function (string $data) use ($body, $headers): bool {
                $expectedMessage = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                ]);

                return $expectedMessage === $data;
            }),
            $priority,
            $expectedDelay,
            90
        )->willReturn(new Job(new JobId($id), 'foobar'));

        $connection = new Connection(['tube_name' => $tube], $client);

        $returnedId = $connection->send($body, $headers, $delay, $priority);

        $this->assertSame($id, $returnedId);
    }

    public function testSendWhenABeanstalkdExceptionOccurs()
    {
        $tube = 'xyz';

        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 1000;
        $expectedDelay = $delay / 1000;

        $exception = new Exception('foo bar');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('put')->with(
            $this->callback(function (string $data) use ($body, $headers): bool {
                $expectedMessage = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                ]);

                return $expectedMessage === $data;
            }),
            1024,
            $expectedDelay,
            90
        )->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));

        $connection->send($body, $headers, $delay);
    }

    public function testKeepalive()
    {
        $id = '123456';

        $tube = 'baz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('touch')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id));

        $connection = new Connection(['tube_name' => $tube], $client);

        $connection->keepalive($id);
    }

    public function testKeepaliveWhenABeanstalkdExceptionOccurs()
    {
        $id = '123456';

        $tube = 'baz123';

        $exception = new ServerException('baz error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('touch')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->keepalive($id);
    }

    public function testSendWithRoundedDelay()
    {
        $tube = 'xyz';
        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 920;
        $expectedDelay = 0;

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with(new TubeName($tube));
        $client->expects($this->once())->method('put')->with(
            $this->anything(),
            $this->anything(),
            $expectedDelay,
            $this->anything(),
        );

        $connection = new Connection(['tube_name' => $tube], $client);
        $connection->send($body, $headers, $delay);
    }
}

interface PheanstalkInterface extends PheanstalkPublisherInterface, PheanstalkSubscriberInterface, PheanstalkManagerInterface
{
    public function disconnect(): void;
}
