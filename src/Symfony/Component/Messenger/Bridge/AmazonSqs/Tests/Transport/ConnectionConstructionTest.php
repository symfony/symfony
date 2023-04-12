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

use AsyncAws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ConnectionConstructionTest extends TestCase
{
    public function testExtraOptions()
    {
        $this->expectException(\InvalidArgumentException::class);
        Connection::fromDsn('sqs://default/queue', ['extra_key']);
    }

    public function testExtraParamsInQuery()
    {
        $this->expectException(\InvalidArgumentException::class);
        Connection::fromDsn('sqs://default/queue?extra_param=some_value');
    }

    public function testConfigureWithCredentials()
    {
        $awsKey = 'some_aws_access_key_value';
        $awsSecret = 'some_aws_secret_value';
        $region = 'eu-west-1';
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => $region, 'accessKeyId' => $awsKey, 'accessKeySecret' => $awsSecret], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue', [
                'access_key' => $awsKey,
                'secret_key' => $awsSecret,
                'region' => $region,
            ], $httpClient)
        );
    }

    public function testConfigureWithTemporaryCredentials()
    {
        $awsKey = 'some_aws_access_key_value';
        $awsSecret = 'some_aws_secret_value';
        $sessionToken = 'some_aws_sessionToken';
        $region = 'eu-west-1';
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => $region, 'accessKeyId' => $awsKey, 'accessKeySecret' => $awsSecret, 'sessionToken' => $sessionToken], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue', [
                'access_key' => $awsKey,
                'secret_key' => $awsSecret,
                'session_token' => $sessionToken,
                'region' => $region,
            ], $httpClient)
        );
    }

    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Amazon SQS DSN is invalid.');

        Connection::fromDsn('sqs://');
    }

    public function testFromDsn()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue', [], $httpClient)
        );
    }

    public function testDsnPrecedence()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue_dsn'], new SqsClient(['region' => 'us-east-2', 'accessKeyId' => 'key_dsn', 'accessKeySecret' => 'secret_dsn'], null, $httpClient)),
            Connection::fromDsn('sqs://key_dsn:secret_dsn@default/queue_dsn?region=us-east-2', ['region' => 'eu-west-3', 'queue_name' => 'queue_options', 'access_key' => 'key_option', 'secret_key' => 'secret_option'], $httpClient)
        );
    }

    public function testFromDsnWithRegion()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'us-west-2', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue?region=us-west-2', [], $httpClient)
        );
    }

    public function testFromDsnAsQueueUrl()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'ab1-MyQueue-A2BCDEF3GHI4', 'account' => '123456789012'], new SqsClient(['region' => 'us-east-2', 'endpoint' => 'https://sqs.us-east-2.amazonaws.com', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient), 'https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4'),
            Connection::fromDsn('https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4', [], $httpClient)
        );
    }

    public function testFromDsnWithCustomEndpoint()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'https://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://localhost/queue', [], $httpClient)
        );
    }

    public function testFromDsnWithSslMode()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'http://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://localhost/queue?sslmode=disable', [], $httpClient)
        );
    }

    public function testFromDsnWithSslModeOnDefault()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue?sslmode=disable', [], $httpClient)
        );
    }

    public function testFromDsnWithCustomEndpointAndPort()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'endpoint' => 'https://localhost:1234', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://localhost:1234/queue', [], $httpClient)
        );
    }

    public function testFromDsnWithOptions()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['account' => '213', 'queue_name' => 'queue', 'delete_on_rejection' => false, 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/213/queue', ['delete_on_rejection' => false, 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], $httpClient)
        );
    }

    public function testFromDsnWithQueryOptions()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['account' => '213', 'queue_name' => 'queue', 'delete_on_rejection' => false, 'buffer_size' => 1, 'wait_time' => 5, 'auto_setup' => false], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/213/queue?delete_on_rejection=false&buffer_size=1&wait_time=5&auto_setup=0', [], $httpClient)
        );
    }

    public function testFromDsnWithQueueNameOption()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default', ['queue_name' => 'queue'], $httpClient)
        );

        $this->assertEquals(
            new Connection(['queue_name' => 'queue'], new SqsClient(['region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default/queue', ['queue_name' => 'queue_ignored'], $httpClient)
        );
    }

    public function testFromDsnWithAccountAndEndpointOption()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $this->assertEquals(
            new Connection(['account' => 12345], new SqsClient(['endpoint' => 'https://custom-endpoint.tld', 'region' => 'eu-west-1', 'accessKeyId' => null, 'accessKeySecret' => null], null, $httpClient)),
            Connection::fromDsn('sqs://default', ['endpoint' => 'https://custom-endpoint.tld', 'account' => 12345], $httpClient)
        );
    }

    public function testFromDsnWithInvalidQueryString()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found in DSN: \[foo\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default?foo=foo');
    }

    public function testFromDsnWithInvalidOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default', ['bar' => 'bar']);
    }

    public function testFromDsnWithInvalidQueryStringAndOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[buffer_size, |');

        Connection::fromDsn('sqs://default?foo=foo', ['bar' => 'bar']);
    }

    /**
     * @dataProvider provideQueueUrl
     */
    public function testInjectQueueUrl(string $dsn, string $queueUrl)
    {
        $connection = Connection::fromDsn($dsn);

        $r = new \ReflectionObject($connection);
        $queueProperty = $r->getProperty('queueUrl');

        $this->assertSame($queueUrl, $queueProperty->getValue($connection));
    }

    public static function provideQueueUrl()
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

        $this->assertNull($queueProperty->getValue($connection));
    }

    public static function provideNotQueueUrl()
    {
        yield ['https://sqs.us-east-2.amazonaws.com/queue'];
        yield ['https://us-east-2/123456/ab1-MyQueue-A2BCDEF3GHI4'];
        yield ['sqs://default/queue'];
    }
}
