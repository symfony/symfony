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
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DynamoDbStoreTest extends TestCase
{
    private HttpClientInterface $httpClient;

    #[Before]
    public function createMockHttpClient(): void
    {
        $this->httpClient = new MockHttpClient([]);
    }

    public function testExtraOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DynamoDbStore('dynamodb://default/lock_keys', [
            'extra_key',
        ]);
    }

    public function testExtraParamsInQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DynamoDbStore('dynamodb://default/lock_keys?extra_param=some_value');
    }

    public function testFromInvalidDsn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Amazon DynamoDB DSN is invalid.');

        new DynamoDbStore('dynamodb://');
    }

    public function testFromUnsupportedDsn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN for DynamoDB.');

        new DynamoDbStore('unsupported://');
    }

    public function testFromDsn(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table', ['http_client' => $this->httpClient])
        );
    }

    public function testDsnPrecedence(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => 'us-east-2', 'accessKeyId' => 'key_dsn', 'accessKeySecret' => 'secret_dsn'], null, $this->httpClient), ['table_name' => 'table_dsn']),
            new DynamoDbStore('dynamodb://key_dsn:secret_dsn@default/table_dsn?region=us-east-2', ['region' => 'eu-west-3', 'table_name' => 'table_options', 'http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithRegion(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => 'us-west-2', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table?region=us-west-2', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithCustomEndpoint(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'https://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSslMode(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'http://localhost', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost/table?sslmode=disable', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithSslModeOnDefault(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://default/table?sslmode=disable', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithCustomEndpointAndPort(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'endpoint' => 'https://localhost:1234', 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table']),
            new DynamoDbStore('dynamodb://localhost:1234/table', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithQueryOptions(): void
    {
        $this->assertEquals(
            new DynamoDbStore(new DynamoDbClient(['region' => null, 'accessKeyId' => null, 'accessKeySecret' => null], null, $this->httpClient), ['table_name' => 'table', 'id_attr' => 'id_dsn']),
            new DynamoDbStore('dynamodb://default/table?id_attr=id_dsn', ['http_client' => $this->httpClient])
        );
    }

    public function testFromDsnWithTableNameOption(): void
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

    public function testFromDsnWithInvalidQueryString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found in DSN: \[foo\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default?foo=foo');
    }

    public function testFromDsnWithInvalidOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default', ['bar' => 'bar']);
    }

    public function testFromDsnWithInvalidQueryStringAndOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Unknown option found: \[bar\]\. Allowed options are \[session_token, |');

        new DynamoDbStore('dynamodb://default?foo=foo', ['bar' => 'bar']);
    }
}
