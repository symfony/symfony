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
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UsernamePasswordJsonAuthenticationListener
     */
    private $listener;

    /**
     * @var \ReflectionMethod
     */
    private $attemptAuthenticationMethod;

    protected function setUp() {
        $tokenStorage = $this->getMock(TokenStorageInterface::class);
        $authenticationManager = $this->getMock(AuthenticationManagerInterface::class);
        $authenticationManager->method('authenticate')->willReturn(true);
        $sessionAuthenticationStrategyInterface = $this->getMock(SessionAuthenticationStrategyInterface::class);
        $httpUtils = $this->getMock(HttpUtils::class);
        $authenticationSuccessHandler = $this->getMock(AuthenticationSuccessHandlerInterface::class);
        $authenticationFailureHandler = $this->getMock(AuthenticationFailureHandlerInterface::class);

        $this->listener = new UsernamePasswordJsonAuthenticationListener($tokenStorage, $authenticationManager, $sessionAuthenticationStrategyInterface, $httpUtils, 'providerKey',  $authenticationSuccessHandler, $authenticationFailureHandler);
        $this->attemptAuthenticationMethod = new \ReflectionMethod($this->listener, 'attemptAuthentication');
        $this->attemptAuthenticationMethod->setAccessible(true);
    }

    public function testAttemptAuthentication()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"_username": "dunglas", "_password": "foo"}');

        $result = $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
        $this->assertTrue($result);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationNoUsername()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"usr": "dunglas", "_password": "foo"}');
        $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationNoPassword()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"_username": "dunglas", "pass": "foo"}');
        $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationUsernameNotAString()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"_username": 1, "_password": "foo"}');
        $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationPasswordNotAString()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(), '{"_username": "dunglas", "_password": 1}');
        $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAttemptAuthenticationUsernameTooLong()
    {
        $username = str_repeat('x', Security::MAX_USERNAME_LENGTH + 1);
        $request = new Request(array(), array(), array(), array(), array(), array(), sprintf('{"_username": "%s", "_password": 1}', $username));

        $this->attemptAuthenticationMethod->invokeArgs($this->listener, array($request));
    }
}
