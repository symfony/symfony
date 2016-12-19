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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\RemoteUserAuthenticationListener;

class RemoteUserAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetPreAuthenticatedData()
    {
        $serverVars = array(
            'REMOTE_USER' => 'TheUser',
        );

        $request = new Request(array(), array(), array(), array(), array(), $serverVars);

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new RemoteUserAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey'
        );

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, array('TheUser', null));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testGetPreAuthenticatedDataNoUser()
    {
        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new RemoteUserAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey'
        );

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
    }

    public function testGetPreAuthenticatedDataWithDifferentKeys()
    {
        $userCredentials = array('TheUser', null);

        $request = new Request(array(), array(), array(), array(), array(), array(
            'TheUserKey' => 'TheUser',
        ));
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new RemoteUserAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            'TheUserKey'
        );

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, $userCredentials);
    }
}
