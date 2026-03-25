<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Profiler;

use Symfony\Bundle\WebProfilerBundle\Profiler\ProfilerJsonRedactor;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class ProfilerJsonRedactorTest extends TestCase
{
    public function testRedactHeadersRedactsSensitiveHeaders()
    {
        $headers = [
            'Authorization' => 'Bearer secret-token',
            'Cookie' => 'session=abc123',
            'Set-Cookie' => 'id=a3fWa; Expires=Thu, 21 Oct 2021 07:28:00 GMT',
            'X-Api-Key' => 'my-api-key',
            'X-Auth-Token' => 'my-auth-token',
            'Content-Type' => 'application/json',
            'Accept' => 'text/html',
        ];

        $result = ProfilerJsonRedactor::redactHeaders($headers);

        $this->assertSame('***REDACTED***', $result['Authorization']);
        $this->assertSame('***REDACTED***', $result['Cookie']);
        $this->assertSame('***REDACTED***', $result['Set-Cookie']);
        $this->assertSame('***REDACTED***', $result['X-Api-Key']);
        $this->assertSame('***REDACTED***', $result['X-Auth-Token']);
        $this->assertSame('application/json', $result['Content-Type']);
        $this->assertSame('text/html', $result['Accept']);
    }

    public function testRedactHeadersPreservesNonSensitiveHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en-US',
            'X-Request-Id' => 'abc-123',
        ];

        $result = ProfilerJsonRedactor::redactHeaders($headers);

        $this->assertSame($headers, $result);
    }

    public function testRedactHeadersIsCaseInsensitive()
    {
        $headers = [
            'authorization' => 'Bearer token',
            'AUTHORIZATION' => 'Bearer token2',
            'Authorization' => 'Bearer token3',
        ];

        $result = ProfilerJsonRedactor::redactHeaders($headers);

        $this->assertSame('***REDACTED***', $result['authorization']);
        $this->assertSame('***REDACTED***', $result['AUTHORIZATION']);
        $this->assertSame('***REDACTED***', $result['Authorization']);
    }

    public function testRedactHeadersOnEmptyArray()
    {
        $this->assertSame([], ProfilerJsonRedactor::redactHeaders([]));
    }

    public function testRedactAllReplacesAllValues()
    {
        $data = [
            'session_id' => 'abc123',
            'user' => 'john',
            'csrf_token' => 'xyz789',
        ];

        $result = ProfilerJsonRedactor::redactAll($data);

        $this->assertSame(['session_id', 'user', 'csrf_token'], array_keys($result));
        foreach ($result as $value) {
            $this->assertSame('***REDACTED***', $value);
        }
    }

    public function testRedactAllPreservesKeys()
    {
        $data = ['foo' => 'bar', 'baz' => 42];

        $result = ProfilerJsonRedactor::redactAll($data);

        $this->assertArrayHasKey('foo', $result);
        $this->assertArrayHasKey('baz', $result);
        $this->assertSame('***REDACTED***', $result['foo']);
        $this->assertSame('***REDACTED***', $result['baz']);
    }

    public function testRedactAllOnEmptyArray()
    {
        $this->assertSame([], ProfilerJsonRedactor::redactAll([]));
    }

    public function testRedactByKeyPatternRedactsSensitiveKeys()
    {
        $data = [
            'APP_SECRET' => 'my-secret',
            'DATABASE_PASSWORD' => 'db-pass',
            'API_KEY' => 'api-key-value',
            'JWT_TOKEN' => 'jwt-value',
            'BEARER_TOKEN' => 'bearer-value',
            'AUTH_TOKEN' => 'auth-value',
            'AWS_CREDENTIAL' => 'aws-cred',
            'RSA_PRIVATE_KEY' => 'private-key',
            'GPG_PASSPHRASE' => 'my-passphrase',
            'DATABASE_DSN' => 'mysql://user:pass@host/db',
            'MAILER_DSN' => 'smtp://user:pass@host',
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
            'DATABASE_URL' => 'mysql://localhost/db',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        $this->assertSame('***REDACTED***', $result['APP_SECRET']);
        $this->assertSame('***REDACTED***', $result['DATABASE_PASSWORD']);
        $this->assertSame('***REDACTED***', $result['API_KEY']);
        $this->assertSame('***REDACTED***', $result['JWT_TOKEN']);
        $this->assertSame('***REDACTED***', $result['BEARER_TOKEN']);
        $this->assertSame('***REDACTED***', $result['AUTH_TOKEN']);
        $this->assertSame('***REDACTED***', $result['AWS_CREDENTIAL']);
        $this->assertSame('***REDACTED***', $result['RSA_PRIVATE_KEY']);
        $this->assertSame('***REDACTED***', $result['GPG_PASSPHRASE']);
        $this->assertSame('***REDACTED***', $result['DATABASE_DSN']);
        $this->assertSame('***REDACTED***', $result['MAILER_DSN']);
        $this->assertSame('prod', $result['APP_ENV']);
        $this->assertSame('0', $result['APP_DEBUG']);
        $this->assertSame('mysql://localhost/db', $result['DATABASE_URL']);
    }

    public function testRedactByKeyPatternIsCaseInsensitive()
    {
        $data = [
            'database_password' => 'secret',
            'my_api_key' => 'key-value',
            'user_auth_token' => 'token-value',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        $this->assertSame('***REDACTED***', $result['database_password']);
        $this->assertSame('***REDACTED***', $result['my_api_key']);
        $this->assertSame('***REDACTED***', $result['user_auth_token']);
    }

    public function testRedactByKeyPatternPreservesNonMatchingKeys()
    {
        $data = [
            'APP_ENV' => 'prod',
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'info',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        $this->assertSame($data, $result);
    }

    public function testRedactByKeyPatternOnEmptyArray()
    {
        $this->assertSame([], ProfilerJsonRedactor::redactByKeyPattern([]));
    }

    public function testRedactByKeyPatternRecursesIntoNestedArrays()
    {
        $data = [
            'user' => [
                'password' => 'secret',
                'name' => 'John',
            ],
            'APP_ENV' => 'prod',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        $this->assertSame('***REDACTED***', $result['user']['password']);
        $this->assertSame('John', $result['user']['name']);
        $this->assertSame('prod', $result['APP_ENV']);
    }

    public function testRedactHeadersRedactsProxyAuthorization()
    {
        $headers = [
            'Proxy-Authorization' => 'Basic dXNlcjpwYXNz',
            'Accept' => 'text/html',
        ];

        $result = ProfilerJsonRedactor::redactHeaders($headers);

        $this->assertSame('***REDACTED***', $result['Proxy-Authorization']);
        $this->assertSame('text/html', $result['Accept']);
    }

    public function testRedactByKeyPatternRedactsConnectionStrings()
    {
        $data = [
            'DATABASE_URL' => 'mysql://user:password@localhost/db',
            'REDIS_URL' => 'redis://default:secret@redis:6379',
            'DEFAULT_URI' => 'http://localhost',
            'REQUEST_URI' => '/en/blog/',
            'SAFE_URL' => 'https://example.com/public',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        $this->assertSame('***REDACTED***', $result['DATABASE_URL']);
        $this->assertSame('***REDACTED***', $result['REDIS_URL']);
        $this->assertSame('http://localhost', $result['DEFAULT_URI']);
        $this->assertSame('/en/blog/', $result['REQUEST_URI']);
        $this->assertSame('https://example.com/public', $result['SAFE_URL']);
    }

    public function testRedactByKeyPatternPreservesUrlsWithoutCredentials()
    {
        $data = [
            'DATABASE_URL' => 'sqlite:///%kernel.project_dir%/data/database.sqlite',
            'MESSENGER_TRANSPORT_DSN' => 'doctrine://default',
        ];

        $result = ProfilerJsonRedactor::redactByKeyPattern($data);

        // sqlite URL has no credentials — preserved (but MESSENGER_TRANSPORT_DSN matches DSN pattern)
        $this->assertSame('sqlite:///%kernel.project_dir%/data/database.sqlite', $result['DATABASE_URL']);
        $this->assertSame('***REDACTED***', $result['MESSENGER_TRANSPORT_DSN']);
    }
}
