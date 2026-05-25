<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\Cas;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\AccessToken\Cas\Cas2Handler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class Cas2HandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Request::setTrustedHosts(['.*']);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testWithValidTicket()
    {
        $response = new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                    <cas:authenticationSuccess>
                        <cas:user>lobster</cas:user>
                        <cas:proxyGrantingTicket>PGTIOU-84678-8a9d</cas:proxyGrantingTicket>
                    </cas:authenticationSuccess>
                </cas:serviceResponse>
            BODY
        );

        $httpClient = new MockHttpClient([$response]);
        $requestStack = new RequestStack();
        $requestStack->push(new Request(['ticket' => 'PGTIOU-84678-8a9d']));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', $httpClient);
        $userbadge = $cas2Handler->getUserBadgeFrom('PGTIOU-84678-8a9d');
        $this->assertEquals(new UserBadge('lobster'), $userbadge);
    }

    public function testWithInvalidTicket()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('CAS Authentication Failure: Ticket ST-1856339 not recognized');

        $response = new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                    <cas:authenticationFailure code="INVALID_TICKET">
                        Ticket ST-1856339 not recognized
                    </cas:authenticationFailure>
                </cas:serviceResponse>
            BODY
        );

        $httpClient = new MockHttpClient([$response]);
        $requestStack = new RequestStack();
        $requestStack->push(new Request(['ticket' => 'ST-1856339']));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', $httpClient);
        $cas2Handler->getUserBadgeFrom('should-not-work');
    }

    public function testWithInvalidCasResponse()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CAS response.');

        $response = new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                </cas:serviceResponse>
            BODY
        );

        $httpClient = new MockHttpClient([$response]);
        $requestStack = new RequestStack();
        $requestStack->push(new Request(['ticket' => 'ST-1856339']));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', $httpClient);
        $cas2Handler->getUserBadgeFrom('should-not-work');
    }

    public function testWithoutTicket()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No ticket found in request.');

        $httpClient = new MockHttpClient();
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', $httpClient);
        $cas2Handler->getUserBadgeFrom('should-not-work');
    }

    public function testWithInvalidPrefix()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CAS response.');

        $response = new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                    <cas:authenticationSuccess>
                        <cas:user>lobster</cas:user>
                        <cas:proxyGrantingTicket>PGTIOU-84678-8a9d</cas:proxyGrantingTicket>
                    </cas:authenticationSuccess>
                </cas:serviceResponse>
            BODY
        );

        $httpClient = new MockHttpClient([$response]);
        $requestStack = new RequestStack();
        $requestStack->push(new Request(['ticket' => 'PGTIOU-84678-8a9d']));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'invalid-one', $httpClient);
        $username = $cas2Handler->getUserBadgeFrom('PGTIOU-84678-8a9d');
        $this->assertEquals('lobster', $username);
    }

    public function testServiceUrlIsBuiltFromCurrentRequest()
    {
        Request::setTrustedHosts(['^app\.example$']);

        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('service='.urlencode('https://app.example/sub/path?foo=bar'), $url);

            return new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                    <cas:authenticationSuccess><cas:user>lobster</cas:user></cas:authenticationSuccess>
                </cas:serviceResponse>
                BODY
            );
        });

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://app.example/sub/path?ticket=ST-1856339&foo=bar'));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', $httpClient);
        $this->assertEquals(new UserBadge('lobster'), $cas2Handler->getUserBadgeFrom('ST-1856339'));
    }

    public function testThrowsWhenNoTrustedHostsConfigured()
    {
        Request::setTrustedHosts([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('CAS authentication requires trusted hosts to be configured');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(['ticket' => 'ST-1856339']));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', new MockHttpClient());
        $cas2Handler->getUserBadgeFrom('ST-1856339');
    }

    public function testRejectsSpoofedHost()
    {
        Request::setTrustedHosts(['^trusted\.example$']);

        $this->expectException(SuspiciousOperationException::class);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://attacker.example/path?ticket=ST-1856339'));

        $cas2Handler = new Cas2Handler($requestStack, 'https://www.example.com/cas', 'cas', new MockHttpClient());
        $cas2Handler->getUserBadgeFrom('ST-1856339');
    }
}
