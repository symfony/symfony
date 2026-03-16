<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Cookie;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Cookie\Cookie;
use Symfony\Component\HttpClient\Cookie\CookieStore;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CookieStoreTest extends TestCase
{
    public function testFromString(): void
    {
        $store = CookieStore::fromString('flavor=chocolate; size=medium');

        $this->assertCount(2, $store);
        $this->assertSame('chocolate', $store->getCookie('flavor')->getValue());
        $this->assertSame('medium', $store->getCookie('size')->getValue());
        $this->assertSame('flavor=chocolate; size=medium', (string) $store);
    }

    public function testFromStringWithSingleCookie(): void
    {
        $store = CookieStore::fromString('flavor=chocolate');

        $this->assertCount(1, $store);
        $this->assertSame('chocolate', $store->getCookie('flavor')->getValue());
    }

    public function testFromStringWithEmptyString(): void
    {
        $store = CookieStore::fromString('');

        $this->assertCount(0, $store);
    }

    public function testFromStringWithWhitespace(): void
    {
        $store = CookieStore::fromString('  flavor=chocolate ;  size=medium  ');

        $this->assertCount(2, $store);
        $this->assertSame('chocolate', $store->getCookie('flavor')->getValue());
        $this->assertSame('medium', $store->getCookie('size')->getValue());
    }

    public function testHasCookie(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate']);

        $this->assertTrue($store->hasCookie('flavor'));
        $this->assertFalse($store->hasCookie('missing'));
    }

    public function testWithoutCookieRemoves(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate', 'size' => 'medium']);
        $newStore = $store->withoutCookie('flavor');

        $this->assertCount(2, $store, 'Original store is unchanged');
        $this->assertCount(1, $newStore);
        $this->assertNull($newStore->getCookie('flavor'));
        $this->assertSame('medium', $newStore->getCookie('size')->getValue());
    }

    public function testWithoutCookieReturnsSameInstanceIfNotFound(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate']);
        $newStore = $store->withoutCookie('missing');

        $this->assertSame($store, $newStore);
    }

    public function testEmptyStore(): void
    {
        $store = new CookieStore();

        $this->assertCount(0, $store);
        $this->assertSame('', (string) $store);
        $this->assertSame([], $store->toArray());
    }

    public function testFromArrayAssociative(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate', 'size' => 'medium']);

        $this->assertCount(2, $store);
        $this->assertSame('flavor=chocolate; size=medium', (string) $store);
    }

    public function testFromArrayIndexedStrings(): void
    {
        $store = CookieStore::fromArray(['flavor=chocolate', 'size=medium']);

        $this->assertSame('flavor=chocolate; size=medium', (string) $store);
    }

    public function testFromArrayCookieObjects(): void
    {
        $store = CookieStore::fromArray([
            new Cookie('flavor', 'chocolate'),
            new Cookie('size', 'medium'),
        ]);

        $this->assertSame('flavor=chocolate; size=medium', (string) $store);
    }

    public function testFromArrayMixed(): void
    {
        $store = CookieStore::fromArray([
            'flavor' => 'chocolate',
            new Cookie('size', 'medium'),
        ]);

        $this->assertCount(2, $store);
    }

    public function testGetCookie(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate']);

        $cookie = $store->getCookie('flavor');

        $this->assertNotNull($cookie);
        $this->assertSame('flavor', $cookie->getName());
        $this->assertSame('chocolate', $cookie->getValue());
    }

    public function testGetCookieReturnsNullForMissing(): void
    {
        $store = new CookieStore();

        $this->assertNull($store->getCookie('missing'));
    }

    public function testToArray(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate', 'size' => 'medium']);

        $this->assertSame(['flavor' => 'chocolate', 'size' => 'medium'], $store->toArray());
    }

    public function testWithCookieAddsNew(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate']);
        $newStore = $store->withCookie('size', 'medium');

        $this->assertCount(1, $store, 'Original store is unchanged');
        $this->assertCount(2, $newStore);
        $this->assertSame('flavor=chocolate; size=medium', (string) $newStore);
    }

    public function testWithCookieReplaces(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate']);
        $newStore = $store->withCookie('flavor', 'vanilla');

        $this->assertSame('chocolate', $store->getCookie('flavor')->getValue(), 'Original unchanged');
        $this->assertSame('vanilla', $newStore->getCookie('flavor')->getValue());
        $this->assertCount(1, $newStore);
    }

    public function testWithCookieObject(): void
    {
        $store = new CookieStore();
        $newStore = $store->withCookie(new Cookie('flavor', 'chocolate'));

        $this->assertCount(1, $newStore);
        $this->assertSame('flavor=chocolate', (string) $newStore);
    }

    public function testExtractFromResponseWithThrowFalse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getHeaders')->with(false)->willReturn([
            'set-cookie' => ['session=abc123; Path=/'],
        ]);

        $store = CookieStore::extractFromResponse($response, false);

        $this->assertCount(1, $store);
    }

    public function testExtractFromResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getHeaders')->with(true)->willReturn([
            'set-cookie' => [
                'session=abc123; Path=/; HttpOnly',
                'token=xyz; Secure; SameSite=Lax',
            ],
        ]);

        $store = CookieStore::extractFromResponse($response);

        $this->assertCount(2, $store);
        $this->assertSame('abc123', $store->getCookie('session')->getValue());
        $this->assertSame('xyz', $store->getCookie('token')->getValue());
        $this->assertSame('session=abc123; token=xyz', (string) $store);
    }

    public function testExtractFromResponseWithNoSetCookies(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getHeaders')->with(true)->willReturn([]);

        $store = CookieStore::extractFromResponse($response);

        $this->assertCount(0, $store);
    }

    public function testExtractFromResponseLastHeaderWinsForDuplicateNames(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getHeaders')->with(true)->willReturn([
            'set-cookie' => [
                'session=old; Path=/',
                'session=new; Path=/',
            ],
        ]);

        $store = CookieStore::extractFromResponse($response);

        $this->assertCount(1, $store);
        $this->assertSame('new', $store->getCookie('session')->getValue(), 'Last Set-Cookie header must win');
    }

    public function testImplementsStringableAndCountable(): void
    {
        $store = new CookieStore();

        $this->assertInstanceOf(\Stringable::class, $store);
        $this->assertInstanceOf(\Countable::class, $store);
        $this->assertInstanceOf(\IteratorAggregate::class, $store);
    }

    public function testGetIteratorYieldsCookiesKeyedByName(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate', 'size' => 'medium']);

        $result = [];
        foreach ($store as $name => $cookie) {
            $result[$name] = $cookie->getValue();
        }

        $this->assertSame(['flavor' => 'chocolate', 'size' => 'medium'], $result);
    }

    public function testFromArrayAcceptsTraversable(): void
    {
        $generator = (static function () {
            yield 'flavor' => 'chocolate';
            yield 'size' => 'medium';
        })();

        $store = CookieStore::fromArray($generator);

        $this->assertCount(2, $store);
        $this->assertSame('chocolate', $store->getCookie('flavor')->getValue());
    }

    public function testConstructorAcceptsTraversable(): void
    {
        $generator = (static function () {
            yield new Cookie('flavor', 'chocolate');
            yield new Cookie('size', 'medium');
        })();

        $store = new CookieStore($generator);

        $this->assertCount(2, $store);
        $this->assertSame('medium', $store->getCookie('size')->getValue());
    }

    public function testToStringUsableAsHeaderValue(): void
    {
        $store = CookieStore::fromArray(['flavor' => 'chocolate', 'size' => 'medium']);

        // Simulate what HttpClientTrait::normalizeHeaders does with a Stringable
        $headerValue = (string) $store;

        $this->assertSame('flavor=chocolate; size=medium', $headerValue);
    }
}
