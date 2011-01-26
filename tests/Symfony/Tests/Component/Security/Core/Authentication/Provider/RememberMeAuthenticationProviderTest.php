<?php

namespace Symfony\Tests\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Role\Role;

class RememberMeAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider();

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->assertNull($provider->authenticate($token));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenKeysDoNotMatch()
    {
        $provider = $this->getProvider(null, 'key1');
        $token = $this->getSupportedToken(null, 'key2');

        $provider->authenticate($token);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\CredentialsExpiredException
     */
    public function testAuthenticateWhenPreChecksFails()
    {
        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\AccountCheckerInterface');
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->will($this->throwException($this->getMock('Symfony\Component\Security\Core\Exception\CredentialsExpiredException', null, array(), '', false)))
        ;

        $provider = $this->getProvider($userChecker);

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AccountExpiredException
     */
    public function testAuthenticateWhenPostChecksFails()
    {
        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\AccountCheckerInterface');
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->will($this->throwException($this->getMock('Symfony\Component\Security\Core\Exception\AccountExpiredException', null, array(), '', false)))
        ;

        $provider = $this->getProvider($userChecker);

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user->expects($this->once())
             ->method('getRoles')
             ->will($this->returnValue(array('ROLE_FOO')))
        ;

        $provider = $this->getProvider();

        $token = $this->getSupportedToken($user);
        $token
            ->expects($this->once())
            ->method('getCredentials')
            ->will($this->returnValue('foo'))
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals(array(new Role('ROLE_FOO')), $authToken->getRoles());
        $this->assertEquals('foo', $authToken->getCredentials());
    }

    protected function getSupportedToken($user = null, $key = 'test')
    {
        if (null === $user) {
            $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
            $user
                ->expects($this->any())
                ->method('getRoles')
                ->will($this->returnValue(array()))
            ;
        }

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', array('getCredentials', 'getProviderKey'), array($user, 'foo', $key));
        $token
            ->expects($this->once())
            ->method('getProviderKey')
            ->will($this->returnValue('foo'))
        ;

        return $token;
    }

    protected function getProvider($userChecker = null, $key = 'test')
    {
        if (null === $userChecker) {
            $userChecker = $this->getMock('Symfony\Component\Security\Core\User\AccountCheckerInterface');
        }

        return new RememberMeAuthenticationProvider($userChecker, $key, 'foo');
    }
}