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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ConnectionTest extends TestCase
{
    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Amazon SQS DSN "sqs://" is invalid.');

        Connection::fromDsn('sqs://');
    }

    public function testFromDsn()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://sqs.eu-west-1.amazonaws.com', 'queue_name' => 'queue'], $httpClient),
            Connection::fromDsn('sqs://default/queue', [], $httpClient)
        );
    }

    public function testFromDsnWithRegion()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://sqs.us-east-1.amazonaws.com', 'queue_name' => 'queue', 'region' => 'us-east-1'], $httpClient),
            Connection::fromDsn('sqs://default/queue?region=us-east-1', [], $httpClient)
        );
    }

    public function testFromDsnWithCustomEndpoint()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://localhost', 'queue_name' => 'queue'], $httpClient),
            Connection::fromDsn('sqs://localhost/queue', [], $httpClient)
        );
    }

    public function testFromDsnWithCustomEndpointAndPort()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://localhost:1234', 'queue_name' => 'queue'], $httpClient),
            Connection::fromDsn('sqs://localhost:1234/queue', [], $httpClient)
        );
    }

    public function testFromDsnWithOptions()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://sqs.eu-west-1.amazonaws.com', 'account' => '213', 'queue_name' => 'queue', 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], $httpClient),
            Connection::fromDsn('sqs://default/213/queue', ['buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], $httpClient)
        );
    }

    public function testFromDsnWithQueryOptions()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $this->assertEquals(
            new Connection(['endpoint' => 'https://sqs.eu-west-1.amazonaws.com', 'account' => '213', 'queue_name' => 'queue', 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], $httpClient),
            Connection::fromDsn('sqs://default/213/queue?buffer_size=1&wait_time=5&auto_setup=0', [], $httpClient)
        );
    }

    private function handleGetQueueUrl(int $index, $mock): string
    {
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $mock->expects($this->at($index))->method('request')
            ->with('POST', 'https://localhost', ['body' => ['Action' => 'GetQueueUrl', 'QueueName' => 'queue']])
            ->willReturn($response);
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);
        $response->expects($this->once())->method('getContent')->willReturn('<GetQueueUrlResponse>
          <GetQueueUrlResult>
            <QueueUrl>https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue</QueueUrl>
          </GetQueueUrlResult>
          <ResponseMetadata>
            <RequestId>470a6f13-2ed9-4181-ad8a-2fdea142988e</RequestId>
          </ResponseMetadata>
        </GetQueueUrlResponse>');

        return 'https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue';
    }

    public function testKeepGettingPendingMessages()
    {
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $queueUrl = $this->handleGetQueueUrl(0, $httpClient);

        $httpClient->expects($this->at(1))->method('request')
            ->with('POST', $queueUrl, ['body' => ['Action' => 'ReceiveMessage', 'VisibilityTimeout' => null, 'MaxNumberOfMessages' => 9, 'WaitTimeSeconds' => 20, 'MessageAttributeName.1' => 'All']])
            ->willReturn($response);
        $response->expects($this->once())->method('getContent')->willReturn('<ReceiveMessageResponse>
          <ReceiveMessageResult>
            <Message>
              <MessageId>5fea7756-0ea4-451a-a703-a558b933e274</MessageId>
              <ReceiptHandle>
                MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+Cw
                Lj1FjgXUv1uSj1gUPAWV66FU/WeR4mq2OKpEGYWbnLmpRCJVAyeMjeU5ZBdtcQ+QE
                auMZc8ZRv37sIW2iJKq3M9MFx1YvV11A2x/KSbkJ0=
              </ReceiptHandle>
              <MD5OfBody>fafb00f5732ab283681e124bf8747ed1</MD5OfBody>
              <Body>{"body":"this is a test","headers":{}}</Body>
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
            <Message>
              <MessageId>5fea7756-0ea4-451a-a703-a558b933e274</MessageId>
              <ReceiptHandle>
                MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+Cw
                Lj1FjgXUv1uSj1gUPAWV66FU/WeR4mq2OKpEGYWbnLmpRCJVAyeMjeU5ZBdtcQ+QE
                auMZc8ZRv37sIW2iJKq3M9MFx1YvV11A2x/KSbkJ0=
              </ReceiptHandle>
              <MD5OfBody>fafb00f5732ab283681e124bf8747ed1</MD5OfBody>
              <Body>{"body":"this is a test","headers":{}}</Body>
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
            <Message>
              <MessageId>5fea7756-0ea4-451a-a703-a558b933e274</MessageId>
              <ReceiptHandle>
                MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+Cw
                Lj1FjgXUv1uSj1gUPAWV66FU/WeR4mq2OKpEGYWbnLmpRCJVAyeMjeU5ZBdtcQ+QE
                auMZc8ZRv37sIW2iJKq3M9MFx1YvV11A2x/KSbkJ0=
              </ReceiptHandle>
              <MD5OfBody>fafb00f5732ab283681e124bf8747ed1</MD5OfBody>
              <Body>{"body":"this is a test","headers":{}}</Body>
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
        </ReceiveMessageResponse>');

        $connection = Connection::fromDsn('sqs://localhost/queue', ['auto_setup' => false], $httpClient);
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
    }

    public function testUnexpectedSqsError()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SQS error happens');

        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $httpClient->expects($this->once())->method('request')->willReturn($response);
        $response->expects($this->once())->method('getStatusCode')->willReturn(400);
        $response->expects($this->once())->method('getContent')->willReturn('<ErrorResponse xmlns="http://queue.amazonaws.com/doc/2012-11-05/">
          <Error>
            <Type>Sender</Type>
            <Code>boom</Code>
            <Message>SQS error happens</Message>
            <Detail/>
          </Error>
          <RequestId>30441e49-5246-5231-9c87-4bd704b81ce9</RequestId>
        </ErrorResponse>');
        $connection = Connection::fromDsn('sqs://localhost/queue', [], $httpClient);
        $connection->get();
    }
}
