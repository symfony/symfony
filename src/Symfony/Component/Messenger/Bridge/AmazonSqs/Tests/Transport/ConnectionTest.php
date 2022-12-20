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

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sqs\Result\GetQueueUrlResult;
use AsyncAws\Sqs\Result\ReceiveMessageResult;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\Message;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ConnectionTest extends TestCase
{
    public function testExtraOptions()
    {
        self::expectException(\InvalidArgumentException::class);
        Connection::fromDsn('sqs://default/queue', [
            'extra_key',
        ]);
    }

    public function testExtraParamsInQuery()
    {
        self::expectException(\InvalidArgumentException::class);
        Connection::fromDsn('sqs://default/queue?extra_param=some_value');
    }

    public function testConfigureWithCredentials()
    {
        $awsKey = 'some_aws_access_key_value';
        $awsSecret = 'some_aws_secret_value';
        $region = 'eu-west-1';
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => $region, 'accessKeyId' => $awsKey, 'accessKeySecret' => $awsSecret], null, $httpClient)), Connection::fromDsn('sqs://default/queue', [
            'access_key' => $awsKey,
            'secret_key' => $awsSecret,
            'region' => $region,
        ], $httpClient));
    }

    public function testFromInvalidDsn()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The given Amazon SQS DSN "sqs://" is invalid.');

        Connection::fromDsn('sqs://');
    }

    public function testFromDsn()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/queue', [], $httpClient));
    }

    public function testDsnPrecedence()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue_dsn'], new SqsClient(['region' => 'us-east-2', 'accessKeyId' => 'key_dsn', 'accessKeySecret' => 'secret_dsn'], null, $httpClient)), Connection::fromDsn('sqs://key_dsn:secret_dsn@default/queue_dsn?region=us-east-2', ['region' => 'eu-west-3', 'queue_name' => 'queue_options', 'access_key' => 'key_option', 'secret_key' => 'secret_option'], $httpClient));
    }

    public function testFromDsnWithRegion()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'us-west-2', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/queue?region=us-west-2', [], $httpClient));
    }

    public function testFromDsnAsQueueUrl()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'ab1-MyQueue-A2BCDEF3GHI4', 'account' => '123456789012'], new SqsClient(['region' => 'us-east-2', 'endpoint' => 'https://sqs.us-east-2.amazonaws.com', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient), 'https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4'), Connection::fromDsn('https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4', [], $httpClient));
    }

    public function testFromDsnWithCustomEndpoint()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'https://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://localhost/queue', [], $httpClient));
    }

    public function testFromDsnWithSslMode()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'http://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://localhost/queue?sslmode=disable', [], $httpClient));
    }

    public function testFromDsnWithSslModeOnDefault()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/queue?sslmode=disable', [], $httpClient));
    }

    public function testFromDsnWithCustomEndpointAndPort()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'https://localhost:1234', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://localhost:1234/queue', [], $httpClient));
    }

    public function testFromDsnWithOptions()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['account' => '213', 'queue_name' => 'queue', 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/213/queue', ['buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], $httpClient));
    }

    public function testFromDsnWithQueryOptions()
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        self::assertEquals(new Connection(['account' => '213', 'queue_name' => 'queue', 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/213/queue?buffer_size=1&wait_time=5&auto_setup=0', [], $httpClient));
    }

    public function testFromDsnWithQueueNameOption()
    {
        $httpClient = self::createMock(HttpClientInterface::class);

        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default', ['queue_name' => 'queue'], $httpClient));

        self::assertEquals(new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default/queue', ['queue_name' => 'queue_ignored'], $httpClient));
    }

    public function testFromDsnWithAccountAndEndpointOption()
    {
        $httpClient = self::createMock(HttpClientInterface::class);

        self::assertEquals(new Connection(['account' => 12345], new SqsClient(['endpoint' => 'https://custom-endpoint.tld', 'region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)), Connection::fromDsn('sqs://default', ['endpoint' => 'https://custom-endpoint.tld', 'account' => 12345], $httpClient));
    }

    public function testFromDsnWithInvalidQueryString()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('|Unknown option found in DSN: \[foo\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default?foo=foo');
    }

    public function testFromDsnWithInvalidOption()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default', ['bar' => 'bar']);
    }

    public function testFromDsnWithInvalidQueryStringAndOption()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default?foo=foo', ['bar' => 'bar']);
    }

    public function testKeepGettingPendingMessages()
    {
        $client = self::createMock(SqsClient::class);
        $client->expects(self::any())
            ->method('getQueueUrl')
            ->with(['QueueName' => 'queue', 'QueueOwnerAWSAccountId' => 123])
            ->willReturn(ResultMockFactory::create(GetQueueUrlResult::class, ['QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue']));
        $client->expects(self::exactly(2))
            ->method('receiveMessage')
            ->withConsecutive(
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue',
                        'MaxNumberOfMessages' => 9,
                        'WaitTimeSeconds' => 20,
                        'MessageAttributeNames' => ['All'],
                        'VisibilityTimeout' => null,
                    ],
                ],
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue',
                        'MaxNumberOfMessages' => 9,
                        'WaitTimeSeconds' => 20,
                        'MessageAttributeNames' => ['All'],
                        'VisibilityTimeout' => null,
                    ],
                ]
            )
            ->willReturnOnConsecutiveCalls(
                ResultMockFactory::create(ReceiveMessageResult::class, ['Messages' => [
                    new Message(['MessageId' => 1, 'Body' => 'this is a test']),
                    new Message(['MessageId' => 2, 'Body' => 'this is a test']),
                    new Message(['MessageId' => 3, 'Body' => 'this is a test']),
                ]]),
                ResultMockFactory::create(ReceiveMessageResult::class, ['Messages' => []])
            );

        $connection = new Connection(['queue_name' => 'queue', 'account' => 123, 'auto_setup' => false], $client);
        self::assertNotNull($connection->get());
        self::assertNotNull($connection->get());
        self::assertNotNull($connection->get());
        self::assertNull($connection->get());
    }

    public function testUnexpectedSqsError()
    {
        self::expectException(HttpException::class);
        self::expectExceptionMessage('SQS error happens');

        $client = self::createMock(SqsClient::class);
        $client->expects(self::any())
            ->method('getQueueUrl')
            ->with(['QueueName' => 'queue', 'QueueOwnerAWSAccountId' => 123])
            ->willReturn(ResultMockFactory::createFailing(GetQueueUrlResult::class, 400, 'SQS error happens'));

        $connection = new Connection(['queue_name' => 'queue', 'account' => 123, 'auto_setup' => false], $client);
        $connection->get();
    }

    /**
     * @dataProvider provideQueueUrl
     */
    public function testInjectQueueUrl(string $dsn, string $queueUrl)
    {
        $connection = Connection::fromDsn($dsn);

        $r = new \ReflectionObject($connection);
        $queueProperty = $r->getProperty('queueUrl');
        $queueProperty->setAccessible(true);

        self::assertSame($queueUrl, $queueProperty->getValue($connection));
    }

    public function provideQueueUrl()
    {
        yield ['https://sqs.us-east-2.amazonaws.com/123456/queue', 'https://sqs.us-east-2.amazonaws.com/123456/queue'];
        yield ['https://KEY:SECRET@sqs.us-east-2.amazonaws.com/123456/queue', 'https://sqs.us-east-2.amazonaws.com/123456/queue'];
        yield ['https://sqs.us-east-2.amazonaws.com/123456/queue?auto_setup=1', 'https://sqs.us-east-2.amazonaws.com/123456/queue'];
    }

    /**
     * @dataProvider provideNotQueueUrl
     */
    public function testNotInjectQueueUrl(string $dsn)
    {
        $connection = Connection::fromDsn($dsn);

        $r = new \ReflectionObject($connection);
        $queueProperty = $r->getProperty('queueUrl');
        $queueProperty->setAccessible(true);

        self::assertNull($queueProperty->getValue($connection));
    }

    public function provideNotQueueUrl()
    {
        yield ['https://sqs.us-east-2.amazonaws.com/queue'];
        yield ['https://us-east-2/123456/ab1-MyQueue-A2BCDEF3GHI4'];
        yield ['sqs://default/queue'];
    }

    public function testGetQueueUrlNotCalled()
    {
        $client = self::createMock(SqsClient::class);
        $connection = new Connection(['queue_name' => 'ab1-MyQueue-A2BCDEF3GHI4', 'account' => '123456789012'], $client, 'https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4');

        $client->expects(self::never())->method('getQueueUrl');
        $client->expects(self::once())->method('deleteMessage');

        $connection->delete('id');
    }

    public function testLoggerWithoutDebugOption()
    {
        $client = new MockHttpClient([$this->getMockedQueueUrlResponse(), $this->getMockedReceiveMessageResponse()]);
        $logger = self::getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['debug'])
            ->getMock();
        $logger->expects(self::never())->method('debug');
        $connection = Connection::fromDsn('sqs://default', ['access_key' => 'foo', 'secret_key' => 'bar', 'auto_setup' => false], $client, $logger);
        $connection->get();
    }

    public function testLoggerWithDebugOption()
    {
        $client = new MockHttpClient([$this->getMockedQueueUrlResponse(), $this->getMockedReceiveMessageResponse()]);
        $logger = self::getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['debug'])
            ->getMock();
        $logger->expects(self::exactly(4))->method('debug');
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
