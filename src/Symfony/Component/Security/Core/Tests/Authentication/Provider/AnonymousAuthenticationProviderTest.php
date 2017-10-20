<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;

class AnonymousAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider('foo');

        $this->assertTrue($provider->supports($this->getSupportedToken('foo')));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     * @expectedExceptionMessage The token is not supported by this authentication provider.
     */
    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider('foo');

        $provider->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
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
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\AnonymousToken')->setMethods(array('getKey'))->disableOriginalConstructor()->getMock();
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
