<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Google\TokenManager;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TokenManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    private static string $testPrivateKey = '';

    public static function setUpBeforeClass(): void
    {
        // Generate a test RSA private key
        self::$testPrivateKey = file_get_contents(__DIR__.'/Fixtures/private_key.pem');
    }

    public function testTokenRetrieved()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oauth2.googleapis.com/token', $url);

            parse_str($options['body'], $body);
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $body['grant_type']);
            $this->assertArrayHasKey('assertion', $body);

            // Verify JWT structure (header.claims.signature)
            $jwtParts = explode('.', $body['assertion']);
            $this->assertCount(3, $jwtParts);

            // Decode and verify header
            $header = json_decode($this->base64UrlDecode($jwtParts[0]), true);
            $this->assertSame('RS256', $header['alg']);
            $this->assertSame('JWT', $header['typ']);

            // Decode and verify claims
            $claims = json_decode($this->base64UrlDecode($jwtParts[1]), true);
            $this->assertSame('service@example.iam.gserviceaccount.com', $claims['iss']);
            $this->assertSame('user@example.com', $claims['sub']);
            $this->assertSame('https://www.googleapis.com/auth/gmail.send', $claims['scope']);
            $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);

            return new JsonMockResponse(['access_token' => 'ACCESSTOKEN', 'expires_in' => 3599]);
        });

        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $token = $manager->getToken();
        $this->assertSame('ACCESSTOKEN', $token);
    }

    public function testTokenCached()
    {
        $counter = 0;
        $client = new MockHttpClient(static function () use (&$counter): ResponseInterface {
            ++$counter;

            return new JsonMockResponse(['access_token' => 't'.$counter, 'expires_in' => 3599]);
        });

        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $this->assertSame('t1', $manager->getToken());
        $this->assertSame('t1', $manager->getToken());

        $this->assertSame(1, $counter);
    }

    public function testTokenExpired()
    {
        $counter = 0;
        $client = new MockHttpClient(static function () use (&$counter): ResponseInterface {
            ++$counter;

            return new JsonMockResponse(['access_token' => 't'.$counter, 'expires_in' => 3599]);
        });

        $clock = static::mockTime('2025-07-31 11:00');
        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $this->assertSame('t1', $manager->getToken());
        $clock->modify('+30 minutes');
        $this->assertSame('t1', $manager->getToken());
        $clock->modify('+30 minutes');
        $this->assertSame('t2', $manager->getToken());

        $this->assertSame(2, $counter);
    }

    public function testTokenRefreshedOneMinuteBeforeExpiry()
    {
        $counter = 0;
        $client = new MockHttpClient(static function () use (&$counter): ResponseInterface {
            ++$counter;

            return new JsonMockResponse(['access_token' => 't'.$counter, 'expires_in' => 3600]);
        });

        $clock = static::mockTime('2025-07-31 11:00');
        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $this->assertSame('t1', $manager->getToken());

        // 30 seconds before expiry, within the one-minute leeway
        $clock->modify('+3570 seconds');
        $this->assertSame('t2', $manager->getToken());

        $this->assertSame(2, $counter);
    }

    public function testIncompleteTokenResponseThrown()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['access_token' => 'ACCESSTOKEN']));

        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('The Google OAuth2 server response misses the "access_token" or "expires_in" key.');
        $manager->getToken();
    }

    public function testNonSuccessCodeThrown()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse('', ['http_code' => 503]));

        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            self::$testPrivateKey,
            'user@example.com',
            $client
        );

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessageMatches('/^Unable to authenticate with Google/');
        $manager->getToken();
    }

    public function testInvalidPrivateKey()
    {
        $client = new MockHttpClient();

        $manager = new TokenManager(
            'service@example.iam.gserviceaccount.com',
            'invalid-private-key',
            'user@example.com',
            $client
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid private key');
        $manager->getToken();
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
