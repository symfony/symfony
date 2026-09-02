<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Bridge\DynamoDb\Tests\Store;

use AsyncAws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DynamoDbStoreTest extends TestCase
{
    private HttpClientInterface $httpClient;

    #[Before]
    public function createMockHttpClient()
    {
        $this->httpClient = new MockHttpClient([]);
    }

    public function testExtraOptions()
    {
        $this->expectException(\InvalidArgumentException::class);
        new DynamoDbStore('dynamodb://default/lock_keys', [
            'extra_key',
        ]);
    }

    public function testExtraParamsInQuery()
    {
        $this->expectException(\InvalidArgumentException::class);
        new DynamoDbStore('dynamodb://default/lock_keys?extra_param=some_value');
    }

    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Amazon DynamoDB DSN is invalid.');

        new DynamoDbStore('dynamodb://');
    }

    public function testFromUnsupportedDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN for DynamoDB.');

        new DynamoDbStore('unsupported://');
    }

    public function testFromDsn()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table', ['http_client' => $this->httpClient])
        );
    }

    public function testDsnPrecedence()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => 'us-east-2', 'accessKeyId' => 'key_dsn', 'accessKeySecret' => 'secret_dsn'], null, $this->httpClient), ['table_name' => 'table_dsn']),
            new DynamoDbStore('dynamodb://key_dsn:secret_dsn@default/table_dsn?region=us-east-2', ['region' => 'eu-west-3', 'table_name' => 'table_options', 'http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithRegion()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => 'us-west-2', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table?region=us-west-2', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithCustomEndpoint()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'https://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSslMode()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'http://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table?sslmode=disable', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSslModeOnDefault()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table?sslmode=disable', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSsl()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'http://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table?ssl=false', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSslTakingPrecedenceOverSslMode()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'https://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table?ssl=true&sslmode=disable', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithInvalidSsl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for the "ssl" option of the "dynamodb" DSN, expected a boolean.');

        new DynamoDbStore('dynamodb://localhost/table?ssl=nope', ['http_client' => $this->httpClient]);
    }

    public function testFromDsnWithCustomEndpointAndPort()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'https://localhost:1234', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost:1234/table', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithQueryOptions()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table', 'id_attr' => 'id_dsn']),
            new DynamoDbStore('dynamodb://default/table?id_attr=id_dsn', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithTableNameOption()
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default', ['table_name' => 'table', 'http_client' => $this->httpClient])
        );

        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table', ['table_name' => 'table_ignored', 'http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithInvalidQueryString()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found in DSN: \[foo\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default?foo=foo');
    }

    public function testFromDsnWithInvalidOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default', ['bar' => 'bar']);
    }

    public function testFromDsnWithInvalidQueryStringAndOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default?foo=foo', ['bar' => 'bar']);
    }

    public function testDeleteIsScopedToTheOwnerToken()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = json_decode($options['body'], true);

            return new MockResponse('{}', ['response_headers' => ['content-type' => 'application/x-amz-json-1.0']]);
        });

        $store = new DynamoDbStore($this->createClient($httpClient), ['table_name' => 'table']);

        $key = new Key(__METHOD__);
        $store->save($key);
        $store->delete($key);

        $this->assertCount(2, $requests);
        $this->assertSame('#token = :token', $requests[1]['ConditionExpression']);
        $this->assertSame(['#token' => 'key_token'], $requests[1]['ExpressionAttributeNames']);
        $this->assertSame($requests[0]['Item']['key_token']['S'], $requests[1]['ExpressionAttributeValues'][':token']['S']);
    }

    public function testDeleteOfALockHeldBySomeoneElseIsIgnored()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            '__type' => 'com.amazonaws.dynamodb.v20120810#ConditionalCheckFailedException',
            'message' => 'The conditional request failed',
        ]), ['http_code' => 400, 'response_headers' => ['content-type' => 'application/x-amz-json-1.0']]));

        $store = new DynamoDbStore($this->createClient($httpClient), ['table_name' => 'table']);

        $store->delete(new Key(__METHOD__));

        $this->addToAssertionCount(1);
    }

    private function createClient(HttpClientInterface $httpClient): DynamoDbClient
    {
        return new DynamoDbClient(['region' => 'us-east-1', 'accessKeyId' => 'key', 'accessKeySecret' => 'secret'], null, $httpClient);
    }
}
