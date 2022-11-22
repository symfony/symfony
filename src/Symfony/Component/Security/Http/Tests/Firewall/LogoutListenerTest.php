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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Security\Http\Tests\Fixtures\TokenInterface;

class LogoutListenerTest extends TestCase
{
    public function testHandleUnmatchedPath()
    {
        [$listener, , $httpUtils, $options] = $this->getListener();

        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST);

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(false);

        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testHandleMatchedPathWithSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();
        $tokenManager = $this->getTokenManager();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($successHandler, $tokenManager);

        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST);

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $listener->addHandler($handler);

        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testHandleMatchedPathWithoutSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($successHandler);

        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST);

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $listener->addHandler($handler);

        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testSuccessHandlerReturnsNonResponse()
    {
        $this->expectException(\RuntimeException::class);
        $successHandler = $this->getSuccessHandler();

        [$listener, , $httpUtils, $options] = $this->getListener($successHandler);

        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST);

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn(null);

        $listener($event);
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

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
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

    private function getHandler()
    {
        return $this->createMock(LogoutHandlerInterface::class);
    }

    private function getHttpUtils()
    {
        return $this->createMock(HttpUtils::class);
    }

    private function getListener($successHandler = null, $tokenManager = null)
    {
        $listener = new LogoutListener(
            $tokenStorage = $this->getTokenStorage(),
            $httpUtils = $this->getHttpUtils(),
            $successHandler ?: $this->getSuccessHandler(),
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

    private function getSuccessHandler()
    {
        return $this->createMock(LogoutSuccessHandlerInterface::class);
    }

    private function getToken()
    {
        return $this->createMock(TokenInterface::class);
    }
}
