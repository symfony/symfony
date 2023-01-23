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

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Exception;
use Pheanstalk\Exception\ClientException;
use Pheanstalk\Exception\DeadlineSoonException;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Job;
use Pheanstalk\JobId;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Response\ArrayResponse;
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

        $this->assertEquals(
            $connection = new Connection([], Pheanstalk::create('foobar', 15555)),
            Connection::fromDsn('beanstalkd://foobar:15555')
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame('default', $configuration['tube_name']);
        $this->assertSame(0, $configuration['timeout']);
        $this->assertSame(90, $configuration['ttr']);
        $this->assertSame('default', $connection->getTube());
    }

    public function testFromDsnWithOptions()
    {
        $this->assertEquals(
            $connection = Connection::fromDsn('beanstalkd://localhost', ['tube_name' => 'foo', 'timeout' => 10, 'ttr' => 5000]),
            Connection::fromDsn('beanstalkd://localhost?tube_name=foo&timeout=10&ttr=5000')
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame('foo', $configuration['tube_name']);
        $this->assertSame(10, $configuration['timeout']);
        $this->assertSame(5000, $configuration['ttr']);
        $this->assertSame('foo', $connection->getTube());
    }

    public function testFromDsnOptionsArrayWinsOverOptionsFromDsn()
    {
        $options = [
            'tube_name' => 'bar',
            'timeout' => 20,
            'ttr' => 6000,
        ];

        $this->assertEquals(
            $connection = new Connection($options, Pheanstalk::create('localhost', 11333)),
            Connection::fromDsn('beanstalkd://localhost:11333?tube_name=foo&timeout=10&ttr=5000', $options)
        );

        $configuration = $connection->getConfiguration();

        $this->assertSame($options['tube_name'], $configuration['tube_name']);
        $this->assertSame($options['timeout'], $configuration['timeout']);
        $this->assertSame($options['ttr'], $configuration['ttr']);
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
        $id = 1234;
        $beanstalkdEnvelope = [
            'body' => 'foo',
            'headers' => 'bar',
        ];

        $tube = 'baz';
        $timeout = 44;

        $job = new Job($id, json_encode($beanstalkdEnvelope));

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('watchOnly')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('reserveWithTimeout')->with($timeout)->willReturn($job);

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $envelope = $connection->get();

        $this->assertSame((string) $id, $envelope['id']);
        $this->assertSame($beanstalkdEnvelope['body'], $envelope['body']);
        $this->assertSame($beanstalkdEnvelope['headers'], $envelope['headers']);
    }

    public function testGetWhenThereIsNoJobInTheTube()
    {
        $tube = 'baz';
        $timeout = 44;

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('watchOnly')->with($tube)->willReturn($client);
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
        $client->expects($this->once())->method('watchOnly')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('reserveWithTimeout')->with($timeout)->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube, 'timeout' => $timeout], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->get();
    }

    public function testAck()
    {
        $id = 123456;

        $tube = 'xyz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id));

        $connection = new Connection(['tube_name' => $tube], $client);

        $connection->ack((string) $id);
    }

    public function testAckWhenABeanstalkdExceptionOccurs()
    {
        $id = 123456;

        $tube = 'xyzw';

        $exception = new ServerException('baz error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->ack((string) $id);
    }

    public function testReject()
    {
        $id = 123456;

        $tube = 'baz';

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id));

        $connection = new Connection(['tube_name' => $tube], $client);

        $connection->reject((string) $id);
    }

    public function testRejectWhenABeanstalkdExceptionOccurs()
    {
        $id = 123456;

        $tube = 'baz123';

        $exception = new ServerException('baz error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
        $client->expects($this->once())->method('delete')->with($this->callback(fn (JobId $jobId): bool => $jobId->getId() === $id))->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->reject((string) $id);
    }

    public function testMessageCount()
    {
        $tube = 'baz';

        $count = 51;

        $response = new ArrayResponse('OK', ['current-jobs-ready' => $count]);

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('statsTube')->with($tube)->willReturn($response);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->assertSame($count, $connection->getMessageCount());
    }

    public function testMessageCountWhenABeanstalkdExceptionOccurs()
    {
        $tube = 'baz1234';

        $exception = new ClientException('foobar error');

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('statsTube')->with($tube)->willThrowException($exception);

        $connection = new Connection(['tube_name' => $tube], $client);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->getMessageCount();
    }

    public function testSend()
    {
        $tube = 'xyz';

        $body = 'foo';
        $headers = ['test' => 'bar'];
        $delay = 1000;
        $expectedDelay = $delay / 1000;

        $id = 110;

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
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
        )->willReturn(new Job($id, 'foobar'));

        $connection = new Connection(['tube_name' => $tube], $client);

        $returnedId = $connection->send($body, $headers, $delay);

        $this->assertSame($id, (int) $returnedId);
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
        $client->expects($this->once())->method('useTube')->with($tube)->willReturn($client);
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
}
