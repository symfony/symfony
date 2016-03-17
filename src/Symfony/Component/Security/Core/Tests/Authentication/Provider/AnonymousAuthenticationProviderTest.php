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

use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;

class AnonymousAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    private $provider;

    protected function setUp()
    {
        $this->provider = $this->getProvider('foo');
    }

    /**
     * @dataProvider provideTokens
     */
    public function testSupports($token, $supported)
    {
        $this->assertEquals($supported, $this->provider->supports($token));
    }

    public function provideTokens()
    {
        return array(
            array($this->getTokenMock('foo', 'AnonymousRequestToken'), true),
            array($this->getTokenMock('foo', 'AuthenticatedAnonymousToken'), false),
            array($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock(), false),
        );
    }

    /**
     * @group legacy
     */
    public function testLegacySupports()
    {
        $this->assertTrue($this->provider->supports($this->getTokenMock('foo', 'AnonymousToken')));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider('foo');

        $this->assertNull($provider->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenSecretIsNotValid()
    {
        $provider = $this->getProvider('foo');

        $provider->authenticate($this->getTokenMock('bar'));
    }

    public function testAuthenticate()
    {
        $provider = $this->getProvider('foo');
        $token = $this->getTokenMock('foo');

        $authenticatedToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\AuthenticatedAnonymousToken', $authenticatedToken);
        $this->assertEquals('foo', $authenticatedToken->getSecret());
    }

    private function getTokenMock($secret, $class = 'AnonymousRequestToken')
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\\'.$class)->setMethods(array('getSecret'))->disableOriginalConstructor()->getMock();
        $token->expects($this->any())
              ->method('getSecret')
              ->will($this->returnValue($secret))
        ;

        return $token;
    }

    protected function getProvider($secret)
    {
        return new AnonymousAuthenticationProvider($secret);
    }
}
