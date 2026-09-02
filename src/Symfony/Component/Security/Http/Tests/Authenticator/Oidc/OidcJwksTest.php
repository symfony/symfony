<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Oidc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcJwks;
use Symfony\Contracts\Cache\ItemInterface;

class OidcJwksTest extends TestCase
{
    #[DataProvider('getKeyUsages')]
    public function testFromResponseKeepsTheKeysUsableForSignature(array $jwk, bool $strict, bool $lax)
    {
        $response = (new MockHttpClient(new JsonMockResponse(['keys' => [['kid' => 'other', 'use' => 'sig'], $jwk]])))
            ->request('GET', 'https://provider.example.com/jwks');

        [$keys] = OidcJwks::fromResponse($response, true);
        $this->assertSame($strict, \in_array($jwk, $keys, true), 'strict mode');

        $response = (new MockHttpClient(new JsonMockResponse(['keys' => [['kid' => 'other', 'use' => 'sig'], $jwk]])))
            ->request('GET', 'https://provider.example.com/jwks');

        [$keys] = OidcJwks::fromResponse($response, false);
        $this->assertSame($lax, \in_array($jwk, $keys, true), 'lax mode');
    }

    /**
     * The very filtering the "oidc" access token handler applies, so that both OIDC
     * entry points accept the same key material.
     */
    public static function getKeyUsages(): iterable
    {
        yield 'use=sig' => [['kid' => 'k', 'use' => 'sig'], true, true];
        yield 'use=enc' => [['kid' => 'k', 'use' => 'enc'], false, false];
        yield 'no usage at all' => [['kid' => 'k'], false, true];
        yield 'key_ops=verify' => [['kid' => 'k', 'key_ops' => ['verify']], true, true];
        yield 'key_ops=sign' => [['kid' => 'k', 'key_ops' => ['sign']], true, true];
        yield 'key_ops=encrypt' => [['kid' => 'k', 'key_ops' => ['encrypt']], false, false];
        yield 'key_ops=encrypt+verify' => [['kid' => 'k', 'key_ops' => ['encrypt', 'verify']], true, true];
    }

    public function testFromResponseRejectsAnOversizedJwks()
    {
        $response = (new MockHttpClient(new MockResponse('{"keys": ["'.str_repeat('a', 1024 * 1024).'"]}')))
            ->request('GET', 'https://provider.example.com/jwks');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC provider JWKS exceeds the maximum size of 1 MiB.');

        OidcJwks::fromResponse($response);
    }

    public function testFromResponseIgnoresKeysThatAreNotObjects()
    {
        $response = (new MockHttpClient(new JsonMockResponse(['keys' => ['garbage', ['kid' => 'k', 'use' => 'sig']]])))
            ->request('GET', 'https://provider.example.com/jwks');

        [$keys] = OidcJwks::fromResponse($response);

        $this->assertSame([['kid' => 'k', 'use' => 'sig']], $keys);
    }

    public function testFromResponseReadsTheProviderMaxAge()
    {
        $response = (new MockHttpClient(new JsonMockResponse(
            ['keys' => [['kid' => 'sig-key', 'use' => 'sig']]],
            ['response_headers' => ['cache-control' => 'public, max-age=600']],
        )))->request('GET', 'https://provider.example.com/jwks');

        [$keys, $ttl] = OidcJwks::fromResponse($response);

        $this->assertSame([['kid' => 'sig-key', 'use' => 'sig']], $keys);
        $this->assertSame(600, $ttl);
    }

    public function testFromResponseReturnsNullTtlWithoutCacheHeaders()
    {
        $response = (new MockHttpClient(new JsonMockResponse(['keys' => []])))
            ->request('GET', 'https://provider.example.com/jwks');

        [$keys, $ttl] = OidcJwks::fromResponse($response);

        $this->assertSame([], $keys);
        $this->assertNull($ttl);
    }

    public function testFetchKeysAppliesProviderTtlToCacheItem()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(
            ['keys' => [['kid' => 'sig-key', 'use' => 'sig']]],
            ['response_headers' => ['cache-control' => 'max-age=120']],
        ));

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(120);

        $keys = OidcJwks::fetchKeys($httpClient, 'https://provider.example.com/jwks', $item);

        $this->assertSame([['kid' => 'sig-key', 'use' => 'sig']], $keys);
    }

    public function testFetchKeysCapsTheProviderTtl()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(
            ['keys' => []],
            ['response_headers' => ['cache-control' => 'max-age=31536000']],
        ));

        $item = $this->createMock(ItemInterface::class);
        // 30 days, and not the year the provider advertises
        $item->expects($this->once())->method('expiresAfter')->with(30 * 24 * 60 * 60);

        OidcJwks::fetchKeys($httpClient, 'https://provider.example.com/jwks', $item);
    }

    public function testFetchKeysFallsBackToDefaultTtl()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['keys' => []]));

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(3600);

        OidcJwks::fetchKeys($httpClient, 'https://provider.example.com/jwks', $item);
    }

    public function testFetchKeysIsUsableAsACacheCallback()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['keys' => [['kid' => 'sig-key', 'use' => 'sig']]]));
        $cache = new ArrayAdapter();

        $keys = $cache->get('jwks', static fn (ItemInterface $item) => OidcJwks::fetchKeys($httpClient, 'https://provider.example.com/jwks', $item));

        $this->assertSame([['kid' => 'sig-key', 'use' => 'sig']], $keys);
    }
}
