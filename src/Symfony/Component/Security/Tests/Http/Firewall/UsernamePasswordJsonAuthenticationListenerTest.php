<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UsernamePasswordJsonAuthenticationListener
     */
    private $listener;

    private function createListener(array $options = array(), $success = true)
    {
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
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

        $this->listener = new UsernamePasswordJsonAuthenticationListener($tokenStorage, $authenticationManager, 'providerKey', $authenticationSuccessHandler, $authenticationFailureHandler, $options);
    }

    public function testHandleSuccess()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testHandleFailure()
    {
        $this->createListener(array(), false);
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ko', $event->getResponse()->getContent());
    }

    public function testUsePath()
    {
        $this->createListener(array('username_path' => 'user.login', 'password_path' => 'user.pwd'));
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"user": {"login": "dunglas", "pwd": "foo"}}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationNoUsername()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"usr": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationNoPassword()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "pass": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationUsernameNotAString()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": 1, "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationPasswordNotAString()
    {
        $this->createListener();
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"username": "dunglas", "password": 1}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationUsernameTooLong()
    {
        $this->createListener();
        $username = str_repeat('x', Security::MAX_USERNAME_LENGTH + 1);
        $request = new Request(array(), array(), array(), array(), array(), array(), sprintf('{"username": "%s", "password": 1}', $username));
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }
}
