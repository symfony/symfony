<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

class SameOriginCsrfTokenManagerTest extends TestCase
{
    private $requestStack;
    private $logger;
    private $csrfTokenManager;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->csrfTokenManager = new SameOriginCsrfTokenManager($this->requestStack, $this->logger);
    }

    public function testInvalidCookieName()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SameOriginCsrfTokenManager($this->requestStack, $this->logger, null, [], SameOriginCsrfTokenManager::CHECK_NO_HEADER, '');
    }

    public function testInvalidCookieNameCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SameOriginCsrfTokenManager($this->requestStack, $this->logger, null, [], SameOriginCsrfTokenManager::CHECK_NO_HEADER, 'invalid name!');
    }

    public function testGetToken()
    {
        $tokenId = 'test_token';
        $token = $this->csrfTokenManager->getToken($tokenId);

        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertSame($tokenId, $token->getId());
    }

    public function testNoRequest()
    {
        $token = new CsrfToken('test_token', 'test_value');

        $this->logger->expects($this->once())->method('error')->with('CSRF validation failed: No request found.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testInvalidTokenLength()
    {
        $request = new Request();
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', '');

        $this->logger->expects($this->once())->method('warning')->with('Invalid double-submit CSRF token.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testInvalidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', 'http://malicious.com');
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $this->logger->expects($this->once())->method('warning')->with('CSRF validation failed: origin info doesn\'t match.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testValidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $this->logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($this->csrfTokenManager->isTokenValid($token));
        $this->assertSame(1 << 8, $request->attributes->get('csrf-token'));
    }

    public function testValidRefererInvalidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:1234');
        $request->headers->set('Referer', $request->getSchemeAndHttpHost());
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $this->logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($this->csrfTokenManager->isTokenValid($token));
        $this->assertSame(1 << 8, $request->attributes->get('csrf-token'));
    }

    public function testValidOriginAfterDoubleSubmit()
    {
        $session = $this->createMock(Session::class);
        $request = new Request();
        $request->setSession($session);
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $request->cookies->set('sess', 'id');
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $session->expects($this->once())->method('getName')->willReturn('sess');
        $session->expects($this->once())->method('get')->with('csrf-token')->willReturn(2 << 8);
        $this->logger->expects($this->once())->method('warning')->with('CSRF validation failed: double-submit info was used in a previous request but is now missing.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testMissingPreviousOrigin()
    {
        $session = $this->createMock(Session::class);
        $request = new Request();
        $request->cookies->set('csrf-token_'.str_repeat('a', 24), 'csrf-token');
        $request->setSession($session);
        $request->cookies->set('sess', 'id');
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $session->expects($this->once())->method('getName')->willReturn('sess');
        $session->expects($this->once())->method('get')->with('csrf-token')->willReturn(1 << 8);
        $this->logger->expects($this->once())->method('warning')->with('CSRF validation failed: origin info was used in a previous request but is now missing.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testValidDoubleSubmit()
    {
        $request = new Request();
        $request->cookies->set('csrf-token_'.str_repeat('a', 24), 'csrf-token');
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $this->logger->expects($this->once())->method('debug')->with('CSRF validation accepted using double-submit info.');
        $this->assertTrue($this->csrfTokenManager->isTokenValid($token));
        $this->assertSame(2 << 8, $request->attributes->get('csrf-token'));
    }

    public function testCheckOnlyHeader()
    {
        $csrfTokenManager = new SameOriginCsrfTokenManager($this->requestStack, $this->logger, null, [], SameOriginCsrfTokenManager::CHECK_ONLY_HEADER);

        $request = new Request();
        $tokenValue = str_repeat('a', 24);
        $request->headers->set('csrf-token', $tokenValue);
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', $tokenValue);

        $this->logger->expects($this->once())->method('debug')->with('CSRF validation accepted using double-submit info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame('csrf-token', $request->cookies->get('csrf-token_'.$tokenValue));

        $this->logger->expects($this->once())->method('warning')->with('CSRF validation failed: wrong token found in header info.');
        $this->assertFalse($csrfTokenManager->isTokenValid(new CsrfToken('test_token', str_repeat('b', 24))));
    }

    /**
     * @testWith [0]
     *           [1]
     *           [2]
     */
    public function testValidOriginMissingDoubleSubmit(int $checkHeader)
    {
        $csrfTokenManager = new SameOriginCsrfTokenManager($this->requestStack, $this->logger, null, [], $checkHeader);

        $request = new Request();
        $tokenValue = str_repeat('a', 24);
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', $tokenValue);

        $this->logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
    }

    public function testMissingEverything()
    {
        $request = new Request();
        $this->requestStack->push($request);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $this->logger->expects($this->once())->method('warning')->with('CSRF validation failed: double-submit and origin info not found.');
        $this->assertFalse($this->csrfTokenManager->isTokenValid($token));
    }

    public function testClearCookies()
    {
        $request = new Request([], [], ['csrf-token' => 2], ['csrf-token_test' => 'csrf-token']);
        $response = new Response();

        $this->csrfTokenManager->clearCookies($request, $response);

        $this->assertTrue($response->headers->has('Set-Cookie'));
    }

    public function testPersistStrategyWithStartedSession()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $session->expects($this->once())->method('set')->with('csrf-token', 2 << 8);

        $this->csrfTokenManager->persistStrategy($request);
    }

    public function testPersistStrategyWithSessionNotStarted()
    {
        $session = $this->createMock(Session::class);

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $session->expects($this->never())->method('set');

        $this->csrfTokenManager->persistStrategy($request);
    }

    public function testOnKernelResponse()
    {
        $request = new Request([], [], ['csrf-token' => 2], ['csrf-token_test' => 'csrf-token']);
        $response = new Response();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->csrfTokenManager->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Set-Cookie'));
    }
}
