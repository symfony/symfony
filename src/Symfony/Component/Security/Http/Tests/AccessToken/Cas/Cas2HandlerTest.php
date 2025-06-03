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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\AccessToken\Cas\Cas2Handler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class Cas2HandlerTest extends TestCase
{
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

        $cas2Handler = new Cas2Handler(requestStack: $requestStack, validationUrl: 'https://www.example.com/cas', client: $httpClient);
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

        $cas2Handler = new Cas2Handler(requestStack: $requestStack, validationUrl: 'https://www.example.com/cas', client: $httpClient);
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

        $cas2Handler = new Cas2Handler(requestStack: $requestStack, validationUrl: 'https://www.example.com/cas', client: $httpClient);
        $cas2Handler->getUserBadgeFrom('should-not-work');
    }

    public function testWithoutTicket()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No ticket found in request.');

        $httpClient = new MockHttpClient();
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $cas2Handler = new Cas2Handler(requestStack: $requestStack, validationUrl: 'https://www.example.com/cas', client: $httpClient);
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

        $cas2Handler = new Cas2Handler(requestStack: $requestStack, validationUrl: 'https://www.example.com/cas', prefix: 'invalid-one', client: $httpClient);
        $username = $cas2Handler->getUserBadgeFrom('PGTIOU-84678-8a9d');
        $this->assertEquals('lobster', $username);
    }
}
