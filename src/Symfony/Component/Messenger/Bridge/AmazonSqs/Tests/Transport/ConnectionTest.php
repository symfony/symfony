<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sqs\Enum\MessageSystemAttributeName;
use AsyncAws\Sqs\Input\ChangeMessageVisibilityRequest;
use AsyncAws\Sqs\Input\DeleteMessageRequest;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\Result\CreateQueueResult;
use AsyncAws\Sqs\Result\GetQueueUrlResult;
use AsyncAws\Sqs\Result\QueueExistsWaiter;
use AsyncAws\Sqs\Result\ReceiveMessageResult;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\Message;
use AsyncAws\Sqs\ValueObject\MessageAttributeValue;
use AsyncAws\Sqs\ValueObject\MessageSystemAttributeValue;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;

class ConnectionTest extends TestCase
{
    public function testKeepGettingPendingMessages()
    {
        $client = $this->createMock(SqsClient::class);
        $client->expects($this->any())
            ->method('getQueueUrl')
            ->with(['QueueName' => 'queue', 'QueueOwnerAWSAccountId' => 123])
            ->willReturn(ResultMockFactory::create(GetQueueUrlResult::class, ['QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue']));

        $firstResult = ResultMockFactory::create(ReceiveMessageResult::class, ['Messages' => [
            new Message(['MessageId' => 1, 'Body' => 'this is a test']),
            new Message(['MessageId' => 2, 'Body' => 'this is a test']),
            new Message(['MessageId' => 3, 'Body' => 'this is a test']),
        ]]);
        $secondResult = ResultMockFactory::create(ReceiveMessageResult::class, ['Messages' => []]);

        $series = [
            [[['QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue',
                'VisibilityTimeout' => null,
                'MaxNumberOfMessages' => 9,
                'MessageAttributeNames' => ['All'],
                'WaitTimeSeconds' => 20]], $firstResult],
            [[['QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue',
                'VisibilityTimeout' => null,
                'MaxNumberOfMessages' => 9,
                'MessageAttributeNames' => ['All'],
                'WaitTimeSeconds' => 20]], $secondResult],
        ];

        $client->expects($this->exactly(2))
            ->method('receiveMessage')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $connection = new Connection(['queue_name' => 'queue', 'account' => 123, 'auto_setup' => false], $client);
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNull($connection->get());
    }

    public function testSetupIfQueueAlreadyExists(): void
    {
        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('queueExists')
            ->willReturn(
                ResultMockFactory::waiter(QueueExistsWaiter::class, QueueExistsWaiter::STATE_SUCCESS),
            );
        $client
            ->expects($this->never())
            ->method('createQueue');

        (new Connection(['queue_name' => 'queue'], $client))
            ->setup();
    }

    public function testSetupIfQueueDoesNotExists(): void
    {
        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->exactly(2))
            ->method('queueExists')
            ->willReturnOnConsecutiveCalls(
                ResultMockFactory::waiter(QueueExistsWaiter::class, QueueExistsWaiter::STATE_FAILURE),
                ResultMockFactory::waiter(QueueExistsWaiter::class, QueueExistsWaiter::STATE_SUCCESS),
            );
        $client
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn(ResultMockFactory::create(CreateQueueResult::class));

        (new Connection(['queue_name' => 'queue'], $client))
            ->setup();
    }

    public function testAck(): void
    {
        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('deleteMessage')
            ->with($this->callback(static function (array|DeleteMessageRequest $input) {
                $input = DeleteMessageRequest::create($input);

                return 'http://queue' === $input->getQueueUrl()
                    && 'some-id' === $input->getReceiptHandle();
            }));
        $client
            ->expects($this->never())
            ->method('getQueueUrl');

        (new Connection(['queue_name' => 'queue', 'auto_setup' => false], $client, 'http://queue'))
            ->ack('some-id');
    }

    public function testRejectWithDeletion(): void
    {
        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('deleteMessage')
            ->with($this->callback(static function (array|DeleteMessageRequest $input) {
                $input = DeleteMessageRequest::create($input);

                return 'http://queue' === $input->getQueueUrl()
                    && 'some-id' === $input->getReceiptHandle();
            }));
        $client
            ->expects($this->never())
            ->method('changeMessageVisibility');
        $client
            ->expects($this->never())
            ->method('getQueueUrl');

        $config = ['queue_name' => 'queue', 'delete_on_rejection' => true, 'auto_setup' => false];
        (new Connection($config, $client, 'http://queue'))->reject('some-id');
    }

    public function testRejectWithVisibilityTimeout(): void
    {
        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('changeMessageVisibility')
            ->with($this->callback(static function (array|ChangeMessageVisibilityRequest $input) {
                $input = ChangeMessageVisibilityRequest::create($input);

                return 'http://queue' === $input->getQueueUrl()
                    && 'some-id' === $input->getReceiptHandle()
                    && 500 === $input->getVisibilityTimeout();
            }));
        $client
            ->expects($this->never())
            ->method('deleteMessage');
        $client
            ->expects($this->never())
            ->method('getQueueUrl');

        $config = ['queue_name' => 'queue', 'delete_on_rejection' => false, 'auto_setup' => false, 'visibility_timeout' => 500];
        (new Connection($config, $client, 'http://queue'))->reject('some-id');
    }

    /** @return iterable<array{string, array<string, string>, int, string|null, string|null, string|null}> */
    public static function provideMessages(): iterable
    {
        yield ['message', [], 0, null, null, null];
        yield ['message', ['X-Magic-Header' => 'some-header'], 0, null, null, null];
        yield ['message', [], 500, null, null, null];
        yield ['message', [], 0, 'group', null, null];
        yield ['message', [], 0, null, 'deduplication', null];
        yield ['message', [], 0, 'group', 'deduplication', null];
        yield ['message', [], 0, null, null, 'xray-trace-id'];
    }

    /**
     * @param array<string, string> $headers
     *
     * @dataProvider provideMessages
     */
    public function testSendToQueue(
        string $body,
        array $headers,
        int $delay,
        ?string $_groupId,
        ?string $_deduplicationId,
        ?string $xrayTraceId,
    ): void {
        $attributes = array_map(
            static fn ($v) => new MessageAttributeValue(['DataType' => 'String', 'StringValue' => $v]),
            $headers,
        );
        $systemAttributes = array_filter([
            MessageSystemAttributeName::AWSTRACE_HEADER => $xrayTraceId
                ? new MessageSystemAttributeValue(['DataType' => 'String', 'StringValue' => $xrayTraceId])
                : null,
        ]);

        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(static function (array|SendMessageRequest $input) use ($body, $attributes, $systemAttributes, $delay) {
                $input = SendMessageRequest::create($input);

                return 'http://queue' === $input->getQueueUrl()
                    && $input->getMessageBody() === $body
                    && $input->getMessageAttributes() == $attributes
                    && $input->getMessageSystemAttributes() == $systemAttributes
                    && $input->getDelaySeconds() === $delay
                    && null === $input->getMessageGroupId()
                    && null === $input->getMessageDeduplicationId();
            }));
        $client
            ->expects($this->never())
            ->method('getQueueUrl');

        (new Connection(['queue_name' => 'queue', 'auto_setup' => false], $client, 'http://queue'))
            ->send($body, $headers, delay: $delay, xrayTraceId: $xrayTraceId);
    }

    /**
     * @param array<string, string> $headers
     *
     * @dataProvider provideMessages
     */
    public function testSendToFifoQueue(
        string $body,
        array $headers,
        int $_delay,
        ?string $groupId,
        ?string $deduplicationId,
        ?string $xrayTraceId,
    ): void {
        $attributes = array_map(
            static fn ($v) => new MessageAttributeValue(['DataType' => 'String', 'StringValue' => $v]),
            $headers,
        );
        $systemAttributes = array_filter([
            MessageSystemAttributeName::AWSTRACE_HEADER => $xrayTraceId
                ? new MessageSystemAttributeValue(['DataType' => 'String', 'StringValue' => $xrayTraceId])
                : null,
        ]);
        $groupId ??= Connection::class.'::send';
        $deduplicationId ??= sha1(json_encode(['body' => $body, 'headers' => $headers]));

        $client = $this->createMock(SqsClient::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(static function (array|SendMessageRequest $input) use ($body, $attributes, $systemAttributes, $groupId, $deduplicationId) {
                $input = SendMessageRequest::create($input);

                return 'http://queue' === $input->getQueueUrl()
                    && $input->getMessageBody() === $body
                    && $input->getMessageAttributes() == $attributes
                    && $input->getMessageSystemAttributes() == $systemAttributes
                    && null === $input->getDelaySeconds()
                    && $input->getMessageGroupId() === $groupId
                    && $input->getMessageDeduplicationId() === $deduplicationId;
            }));
        $client
            ->expects($this->never())
            ->method('getQueueUrl');

        (new Connection(['queue_name' => 'queue.fifo', 'auto_setup' => false], $client, 'http://queue'))
            ->send($body, $headers, messageGroupId: $groupId, messageDeduplicationId: $deduplicationId, xrayTraceId: $xrayTraceId);
    }

    public function testLoggerWithoutDebugOption(): void
    {
        $client = new MockHttpClient([$this->getMockedQueueUrlResponse(), $this->getMockedReceiveMessageResponse()]);
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['debug'])
            ->getMock();
        $logger->expects($this->never())->method('debug');
        $connection = Connection::fromDsn('sqs://default', ['access_key' => 'foo', 'secret_key' => 'bar', 'auto_setup' => false], $client, $logger);
        $connection->get();
    }

    public function testLoggerWithDebugOption(): void
    {
        $client = new MockHttpClient([$this->getMockedQueueUrlResponse(), $this->getMockedReceiveMessageResponse()]);
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['debug'])
            ->getMock();
        $logger->expects($this->exactly(4))->method('debug');
        $connection = Connection::fromDsn('sqs://default?debug=true', ['access_key' => 'foo', 'secret_key' => 'bar', 'auto_setup' => false], $client, $logger);
        $connection->get();
    }

    private function getMockedQueueUrlResponse(): MockResponse
    {
        return new MockResponse(<<<XML
            <GetQueueUrlResponse>
                <GetQueueUrlResult>
                    <QueueUrl>https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue</QueueUrl>
                </GetQueueUrlResult>
                <ResponseMetadata>
                    <RequestId>470a6f13-2ed9-4181-ad8a-2fdea142988e</RequestId>
                </ResponseMetadata>
            </GetQueueUrlResponse>
            XML
        );
    }

    private function getMockedReceiveMessageResponse(): MockResponse
    {
        return new MockResponse(<<<XML
            <ReceiveMessageResponse>
            <ReceiveMessageResult>
                <Message>
                <MessageId>5fea7756-0ea4-451a-a703-a558b933e274</MessageId>
                <ReceiptHandle>
                    MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+Cw
                    Lj1FjgXUv1uSj1gUPAWV66FU/WeR4mq2OKpEGYWbnLmpRCJVAyeMjeU5ZBdtcQ+QE
                    auMZc8ZRv37sIW2iJKq3M9MFx1YvV11A2x/KSbkJ0=
                </ReceiptHandle>
                <MD5OfBody>fafb00f5732ab283681e124bf8747ed1</MD5OfBody>
                <Body>This is a test message</Body>
                <Attribute>
                    <Name>SenderId</Name>
                    <Value>195004372649</Value>
                </Attribute>
                <Attribute>
                    <Name>SentTimestamp</Name>
                    <Value>1238099229000</Value>
                </Attribute>
                <Attribute>
                    <Name>ApproximateReceiveCount</Name>
                    <Value>5</Value>
                </Attribute>
                <Attribute>
                    <Name>ApproximateFirstReceiveTimestamp</Name>
                    <Value>1250700979248</Value>
                </Attribute>
                </Message>
            </ReceiveMessageResult>
            <ResponseMetadata>
                <RequestId>b6633655-283d-45b4-aee4-4e84e0ae6afa</RequestId>
            </ResponseMetadata>
            </ReceiveMessageResponse>
            XML
        );
    }
}
