<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\TokenManager;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TokenManagerTest extends TestCase
{
    public function testTokenRetrieved()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://auth/tenant/oauth2/v2.0/token', $url);

            parse_str($options['body'], $body);
            $this->assertSame('client', $body['client_id']);
            $this->assertSame('key', $body['client_secret']);
            $this->assertSame('https://graph/.default', $body['scope']);
            $this->assertSame('client_credentials', $body['grant_type']);

            return new JsonMockResponse(['access_token' => 'ACCESSTOKEN', 'expires_in' => 3599]);
        });

        $manager = new TokenManager('graph', 'auth', 'tenant', 'client', 'key', $client);

        $manager->getToken();
    }

    public function testTokenCached()
    {
        $counter = 0;
        $client = new MockHttpClient(function () use (&$counter): ResponseInterface {
            ++$counter;

            return new JsonMockResponse(['access_token' => 't'.$counter, 'expires_in' => 3599]);
        });

        $manager = new TokenManager('graph', 'auth', 'tenant', 'client', 'key', $client);
        $this->assertSame('t1', $manager->getToken());
        $this->assertSame('t1', $manager->getToken());

        $this->assertSame(1, $counter);
    }

    public function testTokenExpired()
    {
        $counter = 0;
        $client = new MockHttpClient(function () use (&$counter): ResponseInterface {
            ++$counter;

            return new JsonMockResponse(['access_token' => 't'.$counter, 'expires_in' => 3599]);
        });

        Clock::set(new MockClock('2025-07-31 11:00'));
        $manager = new TokenManager('graph', 'auth', 'tenant', 'client', 'key', $client);
        $this->assertSame('t1', $manager->getToken());
        Clock::set(new MockClock('2025-07-31 11:30'));
        $this->assertSame('t1', $manager->getToken());
        Clock::set(new MockClock('2025-07-31 12:00'));
        $this->assertSame('t2', $manager->getToken());

        $this->assertSame(2, $counter);
    }

    public function testNonSuccessCodeThrown()
    {
        $client = new MockHttpClient(fn (): ResponseInterface => new MockResponse('', ['http_code' => 503]));

        $manager = new TokenManager('graph', 'auth', 'tenant', 'client', 'key', $client);

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessageMatches('/^Unable to authenticate/');
        $manager->getToken();
    }
}
