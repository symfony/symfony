<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LogoutListenerTest extends TestCase
{
    private TokenStorageInterface $tokenStorage;
    private EventDispatcherInterface $eventDispatcher;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private LogoutListener $listener;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->tokenStorage->setToken(new NullToken());
        $this->eventDispatcher = new EventDispatcher();
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->listener = new LogoutListener($this->tokenStorage, new HttpUtils(), $this->eventDispatcher, [
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'logout',
            'logout_path' => '/logout',
            'target_url' => '/',
        ], $this->csrfTokenManager);
    }

    public function testHandleUnmatchedPath()
    {
        $request = new Request();

        $this->assertFalse($this->listener->supports($request));
    }

    public function testHandleMatchedPathWithCsrfValidation()
    {
        $request = Request::create('/logout?_csrf_token=token');

        $this->csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $response = new Response();
        $this->eventDispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->assertTrue($this->listener->supports($request));
        $this->listener->authenticate($event);

        $this->assertSame($response, $event->getResponse());
        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testHandleMatchedPathWithCsrfInQueryParamAndBody()
    {
        $request = Request::create('/logout?_csrf_token=token', 'GET', ['_csrf_token' => 'token2']);

        $this->csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(function ($token) {
                return $token instanceof CsrfToken && 'token2' === $token->getValue();
            }))
            ->willReturn(true);

        $response = new Response();
        $this->eventDispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->assertTrue($this->listener->supports($request));
        $this->listener->authenticate($event);

        $this->assertSame($response, $event->getResponse());
        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testHandleMatchedPathWithoutCsrfValidation()
    {
        $listener = new LogoutListener($this->tokenStorage, new HttpUtils(), $this->eventDispatcher, [
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'logout',
            'logout_path' => '/logout',
            'target_url' => '/',
        ]);

        $request = Request::create('/logout');

        $response = new Response();
        $this->eventDispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->assertTrue($listener->supports($request));
        $listener->authenticate($event);

        $this->assertSame($response, $event->getResponse());
        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testNoResponseSet()
    {
        $request = Request::create('/logout');

        $this->expectException(\RuntimeException::class);

        $this->assertTrue($this->listener->supports($request));
        $this->listener->authenticate(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    #[DataProvider('provideInvalidCsrfTokens')]
    public function testCsrfValidationFails($invalidToken)
    {
        $request = Request::create('/logout');
        if (null !== $invalidToken) {
            $request->query->set('_csrf_token', $invalidToken);
        }

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(false);

        $this->expectException(LogoutException::class);

        $this->assertTrue($this->listener->supports($request));
        $this->listener->authenticate(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public static function provideInvalidCsrfTokens(): array
    {
        return [
            ['invalid'],
            [['in' => 'valid']],
            [null],
        ];
    }
}
