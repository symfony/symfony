<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\Exception\IssuerDirectoryException;
use Symfony\Component\Security\Http\PrivacyPass\IssuerDirectory;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IssuerDirectoryTest extends TestCase
{
    public function testItReadsTheKeysFromTheWellKnownUri()
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new JsonMockResponse(['token-keys' => [self::entry()]]);
        });

        $keys = [...$this->directory($client)];

        $this->assertSame(['GET https://issuer.example/.well-known/private-token-issuer-directory'], $urls);
        $this->assertCount(1, $keys);
        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($keys[0]->getId()));
    }

    public function testItReadsTheKeysOfItsTokenTypeOnly()
    {
        $client = new MockHttpClient(new JsonMockResponse(['token-keys' => [
            ['token-type' => 1, 'token-key' => 'ZXhwZWN0ZWQtdG8tYmUtc2tpcHBlZA=='],
            self::entry(),
        ]]));

        $this->assertCount(1, [...$this->directory($client)]);
    }

    public function testAKeyTheIssuerHasNotRotatedToYetComesLast()
    {
        $client = new MockHttpClient(new JsonMockResponse(['token-keys' => [
            self::entry(PrivacyPassTestVectors::OTHER_TOKEN_KEY, 2000000000),
            self::entry(),
        ]]));

        $keys = [...$this->directory($client)];

        $this->assertCount(2, $keys);
        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($keys[0]->getId()));
    }

    public function testAKeyTheIssuerAlreadyUsesComesFirst()
    {
        $client = new MockHttpClient(new JsonMockResponse(['token-keys' => [
            self::entry(PrivacyPassTestVectors::TOKEN_KEY, 1000000000),
            self::entry(PrivacyPassTestVectors::OTHER_TOKEN_KEY),
        ]]));

        $keys = [...$this->directory($client)];

        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($keys[0]->getId()));
    }

    public function testAKeyThatCannotBeReadIsSkipped()
    {
        $client = new MockHttpClient(new JsonMockResponse(['token-keys' => [
            self::entry(PrivacyPassTestVectors::TOKEN_KEY_RSA_ENCRYPTION),
            self::entry(),
        ]]));

        $this->assertCount(1, [...$this->directory($client)]);
    }

    public function testTheDirectoryIsFetchedOnce()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(['token-keys' => [self::entry()]]);
        });
        $directory = $this->directory($client);

        iterator_to_array($directory);
        iterator_to_array($directory);
        $directory->reset();
        iterator_to_array($directory);

        $this->assertSame(1, $requests);
    }

    public function testItIsFetchedAgainWhenTheCachedDirectoryExpires()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(['token-keys' => [self::entry()]]);
        });
        $clock = new MockClock('2026-10-23 12:00:00');
        $directory = $this->directory($client, new ArrayAdapter(0, true, 0, 0, $clock), 60, $clock);

        iterator_to_array($directory);
        $clock->sleep(61);
        $directory->reset();
        iterator_to_array($directory);

        $this->assertSame(2, $requests);
    }

    public function testTheIssuerCanShortenTheLifetimeOfItsDirectory()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(['token-keys' => [self::entry()]], ['response_headers' => ['cache-control' => 'public, max-age=30']]);
        });
        $clock = new MockClock('2026-10-23 12:00:00');
        $directory = $this->directory($client, new ArrayAdapter(0, true, 0, 0, $clock), 3600, $clock);

        iterator_to_array($directory);
        $clock->sleep(31);
        $directory->reset();
        iterator_to_array($directory);

        $this->assertSame(2, $requests);
    }

    public function testTheIssuerCannotLengthenItBeyondTheConfiguredLifetime()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(['token-keys' => [self::entry()]], ['response_headers' => ['cache-control' => 'max-age=86400']]);
        });
        $clock = new MockClock('2026-10-23 12:00:00');
        $directory = $this->directory($client, new ArrayAdapter(0, true, 0, 0, $clock), 60, $clock);

        iterator_to_array($directory);
        $clock->sleep(61);
        $directory->reset();
        iterator_to_array($directory);

        $this->assertSame(2, $requests);
    }

    public function testADirectoryTheIssuerAsksNotToStoreIsNotCached()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(['token-keys' => [self::entry()]], ['response_headers' => ['cache-control' => 'no-store']]);
        });
        $directory = $this->directory($client);

        iterator_to_array($directory);
        $directory->reset();
        iterator_to_array($directory);

        $this->assertSame(2, $requests);
    }

    public function testItReportsADirectoryItCannotFetch()
    {
        $directory = $this->directory(new MockHttpClient(new MockResponse('', ['http_code' => 503])));

        $this->expectException(IssuerDirectoryException::class);
        $this->expectExceptionMessage('The issuer directory could not be fetched from "https://issuer.example/.well-known/private-token-issuer-directory"');

        iterator_to_array($directory);
    }

    public function testItReportsADirectoryThatIsNotJson()
    {
        $directory = $this->directory(new MockHttpClient(new MockResponse('<html>')));

        $this->expectException(IssuerDirectoryException::class);
        $this->expectExceptionMessage('is not valid JSON');

        iterator_to_array($directory);
    }

    public function testItReportsADirectoryWithoutTokenKeys()
    {
        $directory = $this->directory(new MockHttpClient(new JsonMockResponse(['issuer-request-uri' => 'https://issuer.example/request'])));

        $this->expectException(IssuerDirectoryException::class);
        $this->expectExceptionMessage('announces no "token-keys"');

        iterator_to_array($directory);
    }

    public function testItReportsADirectoryWithNoUsableKey()
    {
        $directory = $this->directory(new MockHttpClient(new JsonMockResponse(['token-keys' => [self::entry(PrivacyPassTestVectors::TOKEN_KEY_RSA_ENCRYPTION)]])));

        $this->expectException(IssuerDirectoryException::class);
        $this->expectExceptionMessage('announces no key a token of type "0x0002" can be verified with: The token key must use the id-RSASSA-PSS object identifier.');

        iterator_to_array($directory);
    }

    public function testItReportsADirectoryThatIsTooLarge()
    {
        $directory = $this->directory(new MockHttpClient(new MockResponse(str_repeat('a', 1024 * 1024 + 1))));

        $this->expectException(IssuerDirectoryException::class);
        $this->expectExceptionMessage('exceeds the maximum size of 1 MiB');

        iterator_to_array($directory);
    }

    public function testItRejectsAnIssuerNameItCannotBuildAUrlFrom()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The issuer name "issuer.example/../other" must be an ASCII host with an optional port, and no user information.');

        new IssuerDirectory(new MockHttpClient(), new ArrayAdapter(), 'issuer.example/../other', new BlindRsaTokenType());
    }

    private function directory(HttpClientInterface $client, ?ArrayAdapter $cache = null, ?int $cacheTtl = 3600, ?MockClock $clock = null): IssuerDirectory
    {
        return new IssuerDirectory($client, $cache ?? new ArrayAdapter(), 'issuer.example', new BlindRsaTokenType(), $cacheTtl, $clock);
    }

    private static function entry(?string $tokenKey = null, ?int $notBefore = null): array
    {
        $entry = ['token-type' => 2, 'token-key' => strtr(base64_encode(hex2bin($tokenKey ?? PrivacyPassTestVectors::TOKEN_KEY)), '+/', '-_')];

        return null !== $notBefore ? $entry + ['not-before' => $notBefore] : $entry;
    }
}
