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
    /**
     * @dataProvider dataProviderGetPreAuthenticatedData
     */
    public function testGetPreAuthenticatedData($user)
    {
        $serverVars = array();
        if ('' !== $user) {
            $serverVars['REMOTE_USER'] = $user;
        }

        $request = new Request(array(), array(), array(), array(), array(), $serverVars);

        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');

        $authenticationManager = $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');

        $listener = new RemoteUserAuthenticationListener(
            $context,
            $authenticationManager,
            'TheProviderKey'
        );

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, array($request));
        $this->assertSame($result, array($user, null));
    }

    public static function dataProviderGetPreAuthenticatedData()
    {
        return array(
            'validValues' => array('TheUser'),
        );
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testGetPreAuthenticatedDataNoUser()
    {
        $request = new Request(array(), array(), array(), array(), array(), array());

        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');

        $authenticationManager = $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');

        $listener = new RemoteUserAuthenticationListener(
            $context,
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
            'TheCredentialsKey' => 'TheCredentials'
        ));
        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');

        $authenticationManager = $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');

        $listener = new RemoteUserAuthenticationListener(
            $context,
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
