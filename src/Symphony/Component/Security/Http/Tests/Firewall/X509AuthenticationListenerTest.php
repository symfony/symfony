<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Http\Firewall\X509AuthenticationListener;

class X509AuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider dataProviderGetPreAuthenticatedData
     */
    public function testGetPreAuthenticatedData($user, $credentials)
    {
        $serverVars = array();
        if ('' !== $user) {
            $serverVars['SSL_CLIENT_S_DN_Email'] = $user;
        }
        if ('' !== $credentials) {
            $serverVars['SSL_CLIENT_S_DN'] = $credentials;
        }

        $request = new Request(array(), array(), array(), array(), array(), $serverVars);

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, array($user, $credentials));
    }

    public static function dataProviderGetPreAuthenticatedData()
    {
        return array(
            'validValues' => array('TheUser', 'TheCredentials'),
            'noCredentials' => array('TheUser', ''),
        );
    }

    /**
     * @dataProvider dataProviderGetPreAuthenticatedDataNoUser
     */
    public function testGetPreAuthenticatedDataNoUser($emailAddress)
    {
        $credentials = 'CN=Sample certificate DN/emailAddress='.$emailAddress;
        $request = new Request(array(), array(), array(), array(), array(), array('SSL_CLIENT_S_DN' => $credentials));

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, array($emailAddress, $credentials));
    }

    public static function dataProviderGetPreAuthenticatedDataNoUser()
    {
        return array(
            'basicEmailAddress' => array('cert@example.com'),
            'emailAddressWithPlusSign' => array('cert+something@example.com'),
        );
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testGetPreAuthenticatedDataNoData()
    {
        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
    }

    public function testGetPreAuthenticatedDataWithDifferentKeys()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array(
            'TheUserKey' => 'TheUser',
            'TheCredentialsKey' => 'TheCredentials',
        ));
        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey', 'TheUserKey', 'TheCredentialsKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, $userCredentials);
    }
}
