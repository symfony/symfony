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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

class SameOriginCsrfTokenManagerTest extends TestCase
{
    public function testInvalidCookieName()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger(), null, [], SameOriginCsrfTokenManager::CHECK_NO_HEADER, '');
    }

    public function testInvalidCookieNameCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger(), null, [], SameOriginCsrfTokenManager::CHECK_NO_HEADER, 'invalid name!');
    }

    public function testGetToken()
    {
        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger());
        $tokenId = 'test_token';
        $token = $csrfTokenManager->getToken($tokenId);

        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertSame($tokenId, $token->getId());
    }

    public function testNoRequest()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), $logger);
        $token = new CsrfToken('test_token', 'test_value');

        $logger->expects($this->once())->method('error')->with('CSRF validation failed: No request found.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testInvalidTokenLength()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', '');

        $logger->expects($this->once())->method('warning')->with('Invalid double-submit CSRF token.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testInvalidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', 'http://malicious.com');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: origin info doesn\'t match.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testValidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame(1 | (1 << 8), $request->attributes->get('csrf-token'));
    }

    public function testValidRefererInvalidOrigin()
    {
        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:1234');
        $request->headers->set('Referer', $request->getSchemeAndHttpHost());
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame(1 | (1 << 8), $request->attributes->get('csrf-token'));
    }

    public function testSecFetchSiteSameOrigin()
    {
        $request = new Request();
        $request->headers->set('Sec-Fetch-Site', 'same-origin');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame(1 | (1 << 8), $request->attributes->get('csrf-token'));
    }

    public function testSecFetchSiteCrossSite()
    {
        $request = new Request();
        $request->headers->set('Sec-Fetch-Site', 'cross-site');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: origin info doesn\'t match.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testValidOriginAfterDoubleSubmit()
    {
        $session = new Session(new MockArraySessionStorage('sess'));
        $request = new Request();
        $request->setSession($session);
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $request->cookies->set('sess', 'id');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $session->set('csrf-token', 2 << 8);
        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: double-submit info was used in a previous request but is now missing.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testMissingPreviousOrigin()
    {
        $session = new Session(new MockArraySessionStorage('sess'));
        $request = new Request();
        $request->cookies->set('csrf-token_'.str_repeat('a', 24), 'csrf-token');
        $request->setSession($session);
        $request->cookies->set('sess', 'id');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $session->set('csrf-token', 1 << 8);
        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: origin info was used in a previous request but is now missing.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    public function testValidDoubleSubmit()
    {
        $request = new Request();
        $request->cookies->set('csrf-token_'.str_repeat('a', 24), 'csrf-token');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using double-submit info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame(2 << 8, $request->attributes->get('csrf-token'));
    }

    public function testCheckOnlyHeader()
    {
        $request = new Request();
        $tokenValue = str_repeat('a', 24);
        $request->headers->set('csrf-token', $tokenValue);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger, null, [], SameOriginCsrfTokenManager::CHECK_ONLY_HEADER);

        $token = new CsrfToken('test_token', $tokenValue);

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using double-submit info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
        $this->assertSame('csrf-token', $request->cookies->get('csrf-token_'.$tokenValue));

        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: wrong token found in header info.');
        $this->assertFalse($csrfTokenManager->isTokenValid(new CsrfToken('test_token', str_repeat('b', 24))));
    }

    #[TestWith([0])]
    #[TestWith([1])]
    #[TestWith([2])]
    public function testValidOriginMissingDoubleSubmit(int $checkHeader)
    {
        $request = new Request();
        $tokenValue = str_repeat('a', 24);
        $request->headers->set('Origin', $request->getSchemeAndHttpHost());
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger, null, [], $checkHeader);

        $token = new CsrfToken('test_token', $tokenValue);

        $logger->expects($this->once())->method('debug')->with('CSRF validation accepted using origin info.');
        $this->assertTrue($csrfTokenManager->isTokenValid($token));
    }

    public function testMissingEverything()
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $logger = $this->createMock(LoggerInterface::class);
        $csrfTokenManager = new SameOriginCsrfTokenManager($requestStack, $logger);

        $token = new CsrfToken('test_token', str_repeat('a', 24));

        $logger->expects($this->once())->method('warning')->with('CSRF validation failed: double-submit and origin info not found.');
        $this->assertFalse($csrfTokenManager->isTokenValid($token));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testClearCookies()
    {
        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger());

        $request = new Request([], [], ['csrf-token' => 2], ['csrf-token_test' => 'csrf-token']);
        $response = new Response();

        $csrfTokenManager->clearCookies($request, $response);

        $this->assertTrue($response->headers->has('Set-Cookie'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testPersistStrategyWithStartedSession()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger());

        $csrfTokenManager->persistStrategy($request);
        $this->assertSame(2 << 8, $session->get('csrf-token'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testPersistStrategyWithSessionNotStarted()
    {
        $session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger());
        $csrfTokenManager->persistStrategy($request);

        $this->assertSame([], $session->all());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testOnKernelResponse()
    {
        $csrfTokenManager = new SameOriginCsrfTokenManager(new RequestStack(), new NullLogger());

        $request = new Request([], [], ['csrf-token' => 2], ['csrf-token_test' => 'csrf-token']);
        $response = new Response();
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $csrfTokenManager->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Set-Cookie'));
    }
}
