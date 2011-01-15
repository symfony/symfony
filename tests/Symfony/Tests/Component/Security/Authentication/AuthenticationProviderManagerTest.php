<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication;

use Symfony\Component\Security\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Exception\AccountStatusException;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;

class AuthenticationProviderManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testProviderAccessors()
    {
        $manager = new AuthenticationProviderManager();
        $manager->add($provider = $this->getMock('Symfony\Component\Security\Authentication\Provider\AuthenticationProviderInterface'));
        $this->assertSame(array($provider), $manager->all());

        $manager->setProviders($providers = array($this->getMock('Symfony\Component\Security\Authentication\Provider\AuthenticationProviderInterface')));
        $this->assertSame($providers, $manager->all());
    }

    /**
     * @expectedException LogicException
     */
    public function testAuthenticateWithoutProviders()
    {
        $manager = new AuthenticationProviderManager();
        $manager->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
    }

    public function testAuthenticateWhenNoProviderSupportsToken()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(false),
        ));

        try {
            $manager->authenticate($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
            $this->fail();
        } catch (ProviderNotFoundException $e) {
            $this->assertSame($token, $e->getExtraInformation());
        }
    }

    public function testAuthenticateWhenProviderReturnsAccountStatusException()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Exception\AccountStatusException'),
        ));

        try {
            $manager->authenticate($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
            $this->fail();
        } catch (AccountStatusException $e) {
            $this->assertSame($token, $e->getExtraInformation());
        }
    }

    public function testAuthenticateWhenProviderReturnsAuthenticationException()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Exception\AuthenticationException'),
        ));

        try {
            $manager->authenticate($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
            $this->fail();
        } catch (AuthenticationException $e) {
            $this->assertSame($token, $e->getExtraInformation());
        }
    }

    public function testAuthenticateWhenOneReturnsAuthenticationExceptionButNotAll()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Exception\AuthenticationException'),
            $this->getAuthenticationProvider(true, $expected = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')),
        ));

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertSame($expected, $token);
    }

    public function testAuthenticateReturnsTokenForTheLastMatch()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')),
            $this->getAuthenticationProvider(true, $expected = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')),
        ));

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertSame($expected, $token);
    }

    public function testEraseCredentialFlag()
    {
        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar')),
        ));

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertEquals('', $token->getCredentials());

        $manager = new AuthenticationProviderManager(array(
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar')),
        ), false);

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertEquals('bar', $token->getCredentials());
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = $this->getMock('Symfony\Component\Security\Authentication\Provider\AuthenticationProviderInterface');
        $provider->expects($this->once())
                 ->method('supports')
                 ->will($this->returnValue($supports))
        ;

        if (null !== $token) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->will($this->returnValue($token))
            ;
        } elseif (null !== $exception) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->will($this->throwException($this->getMock($exception, null, array(), '', false)))
            ;
        }

        return $provider;
    }
}
