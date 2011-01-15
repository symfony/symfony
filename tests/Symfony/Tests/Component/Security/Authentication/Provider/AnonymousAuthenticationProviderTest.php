<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Authentication\Provider\AnonymousAuthenticationProvider;

class AnonymousAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider('foo');

        $this->assertTrue($provider->supports($this->getSupportedToken('foo')));
        $this->assertFalse($provider->supports($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider('foo');

        $this->assertNull($provider->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')));
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenKeyIsNotValid()
    {
        $provider = $this->getProvider('foo');

        $this->assertNull($provider->authenticate($this->getSupportedToken('bar')));
    }

    public function testAuthenticate()
    {
        $provider = $this->getProvider('foo');
        $token = $this->getSupportedToken('foo');

        $this->assertSame($token, $provider->authenticate($token));
    }

    protected function getSupportedToken($key)
    {
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\AnonymousToken', array('getKey'), array(), '', false);
        $token->expects($this->any())
              ->method('getKey')
              ->will($this->returnValue($key))
        ;

        return $token;
    }

    protected function getProvider($key)
    {
        return new AnonymousAuthenticationProvider($key);
    }
}
