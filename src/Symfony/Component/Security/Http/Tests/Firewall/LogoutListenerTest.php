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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\HttpUtils;

class LogoutListenerTest extends TestCase
{
    public function testHandleUnmatchedPath()
    {
        $dispatcher = $this->getEventDispatcher();
        [$listener, , $httpUtils, $options] = $this->getListener($dispatcher);

        $logoutEventDispatched = false;
        $dispatcher->addListener(LogoutEvent::class, function () use (&$logoutEventDispatched) {
            $logoutEventDispatched = true;
        });

        $request = new Request();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(false);

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse($logoutEventDispatched, 'LogoutEvent should not have been dispatched.');
    }

    public function testHandleMatchedPathWithCsrfValidation()
    {
        $tokenManager = $this->getTokenManager();
        $dispatcher = $this->getEventDispatcher();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($dispatcher, $tokenManager);

        $request = new Request();
        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $response = new Response();
        $dispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testHandleMatchedPathWithoutCsrfValidation()
    {
        $dispatcher = $this->getEventDispatcher();
        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($dispatcher);

        $request = new Request();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $response = new Response();
        $dispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testNoResponseSet()
    {
        $this->expectException(\RuntimeException::class);

        [$listener, , $httpUtils, $options] = $this->getListener();

        $request = new Request();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @dataProvider provideInvalidCsrfTokens
     */
    public function testCsrfValidationFails($invalidToken)
    {
        $this->expectException(LogoutException::class);
        $tokenManager = $this->getTokenManager();

        [$listener, , $httpUtils, $options] = $this->getListener(null, $tokenManager);

        $request = new Request();
        if (null !== $invalidToken) {
            $request->query->set('_csrf_token', $invalidToken);
        }

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager
            ->method('isTokenValid')
            ->willReturn(false);

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public static function provideInvalidCsrfTokens(): array
    {
        return [
            ['invalid'],
            [['in' => 'valid']],
            [null],
        ];
    }

    private function getTokenManager()
    {
        return $this->createMock(CsrfTokenManagerInterface::class);
    }

    private function getTokenStorage()
    {
        return $this->createMock(TokenStorageInterface::class);
    }

    private function getHttpUtils()
    {
        return $this->createMock(HttpUtils::class);
    }

    private function getListener($eventDispatcher = null, $tokenManager = null)
    {
        $listener = new LogoutListener(
            $tokenStorage = $this->getTokenStorage(),
            $httpUtils = $this->getHttpUtils(),
            $eventDispatcher ?? $this->getEventDispatcher(),
            $options = [
                'csrf_parameter' => '_csrf_token',
                'csrf_token_id' => 'logout',
                'logout_path' => '/logout',
                'target_url' => '/',
            ],
            $tokenManager
        );

        return [$listener, $tokenStorage, $httpUtils, $options];
    }

    private function getEventDispatcher()
    {
        return new EventDispatcher();
    }

    private function getToken()
    {
        return $this->createMock(TokenInterface::class);
    }
}
