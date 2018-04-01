<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Tests\Http\Firewall;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\KernelInterface;
use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symphony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener;
use Symphony\Component\Security\Http\HttpUtils;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListenerTest extends TestCase
{
    /**
     * @var UsernamePasswordJsonAuthenticationListener
     */
    private $listener;

    private function createListener(array $options = array(), $success = true, $matchCheckPath = true)
    {
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $httpUtils = $this->getMockBuilder(HttpUtils::class)->getMock();
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->will($this->returnValue($matchCheckPath))
        ;
        $authenticationManager = $this->getMockBuilder(AuthenticationManagerInterface::class)->getMock();

        $authenticatedToken = $this->getMockBuilder(TokenInterface::class)->getMock();

        if ($success) {
            $authenticationManager->method('authenticate')->willReturn($authenticatedToken);
        } else {
            $authenticationManager->method('authenticate')->willThrowException(new AuthenticationException());
        }

        $authenticationSuccessHandler = $this->getMockBuilder(AuthenticationSuccessHandlerInterface::class)->getMock();
        $authenticationSuccessHandler->method('onAuthenticationSuccess')->willReturn(new Response('ok'));
        $authenticationFailureHandler = $this->getMockBuilder(AuthenticationFailureHandlerInterface::class)->getMock();
        $authenticationFailureHandler->method('onAuthenticationFailure')->willReturn(new Response('ko'));

        $this->listener = new UsernamePasswordJsonAuthenticationListener($tokenStorage, $authenticationManager, $httpUtils, 'providerKey', $authenticationSuccessHandler, $authenticationFailureHandler, $options);
    }

    public function testHandleSuccessIfRequestContentTypeIsJson()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testSuccessIfRequestFormatIsJsonLD()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "password": "foo"}');
        $request->setRequestFormat('json-ld');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testHandleFailure()
    {
        $this->createListener(array(), false);
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ko', $event->getResponse()->getContent());
    }

    public function testUsePath()
    {
        $this->createListener(array('username_path' => 'user.login', 'password_path' => 'user.pwd'));
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"user": {"login": "dunglas", "pwd": "foo"}}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage Invalid JSON
     */
    public function testAttemptAuthenticationNoJson()
    {
        $this->createListener();
        $request = new Request();
        $request->setRequestFormat('json');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "username" must be provided
     */
    public function testAttemptAuthenticationNoUsername()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"usr": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "password" must be provided
     */
    public function testAttemptAuthenticationNoPassword()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": "dunglas", "pass": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "username" must be a string.
     */
    public function testAttemptAuthenticationUsernameNotAString()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": 1, "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "password" must be a string.
     */
    public function testAttemptAuthenticationPasswordNotAString()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": "dunglas", "password": 1}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationUsernameTooLong()
    {
        $this->createListener();
        $username = str_repeat('x', Security::MAX_USERNAME_LENGTH + 1);
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), sprintf('{"username": "%s", "password": 1}', $username));
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertSame('ko', $event->getResponse()->getContent());
    }

    public function testDoesNotAttemptAuthenticationIfRequestPathDoesNotMatchCheckPath()
    {
        $this->createListener(array('check_path' => '/'), true, false);
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'));
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);
        $event->setResponse(new Response('original'));

        $this->listener->handle($event);
        $this->assertSame('original', $event->getResponse()->getContent());
    }

    public function testDoesNotAttemptAuthenticationIfRequestContentTypeIsNotJson()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);
        $event->setResponse(new Response('original'));

        $this->listener->handle($event);
        $this->assertSame('original', $event->getResponse()->getContent());
    }

    public function testAttemptAuthenticationIfRequestPathMatchesCheckPath()
    {
        $this->createListener(array('check_path' => '/'));
        $request = new Request(array(), array(), array(), array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertSame('ok', $event->getResponse()->getContent());
    }
}
