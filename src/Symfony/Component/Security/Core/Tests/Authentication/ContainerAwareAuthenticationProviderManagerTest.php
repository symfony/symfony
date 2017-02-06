<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use Symfony\Component\Security\Core\Authentication\ContainerAwareAuthenticationProviderManager;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ContainerAwareAuthenticationProviderManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testAuthenticateWhenNoProviderSupportsToken()
    {
        $container = $this->getContainer(array(
            'foo_provider' => $this->getAuthenticationProvider(false),
        ));

        $manager = new ContainerAwareAuthenticationProviderManager(
            array('foo_provider'),
            $container
        );

        try {
            $manager->authenticate($token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));
            $this->fail();
        } catch (ProviderNotFoundException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateReturnsTokenOfTheFirstMatchingProvider()
    {
        $third = $this->getMock('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface');
        $third
            ->expects($this->never())
            ->method('supports')
        ;

        $container = $this->getContainer(array(
            'first_provider' => $this->getAuthenticationProvider(false),
            'second_provider' => $this->getAuthenticationProvider(true, $expected = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')),
            'third_provider' => $third,
        ));

        $manager = new ContainerAwareAuthenticationProviderManager(
            array('first_provider', 'second_provider', 'third_provider'),
            $container
        );

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));
        $this->assertSame($expected, $token);
    }

    public function testEraseCredentialFlag()
    {
        $container = $this->getContainer(array(
            'provider_service' => $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ));
        $manager = new ContainerAwareAuthenticationProviderManager(
            array('provider_service'),
            $container
        );

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));
        $this->assertEquals('', $token->getCredentials());

        $container = $this->getContainer(array(
            'provider_service' => $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ));
        $manager = new ContainerAwareAuthenticationProviderManager(
            array('provider_service'),
            $container,
            false
        );

        $token = $manager->authenticate($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));
        $this->assertEquals('bar', $token->getCredentials());
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = $this->getMock('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface');
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
                     ->will($this->throwException($this->getMock($exception, null, array(), '')))
            ;
        }

        return $provider;
    }

    public function getContainer(array $servicesMap)
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($servicesMap) {
                return $servicesMap[$id];
            });

        return $container;
    }
}
