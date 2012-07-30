<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\LdapAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;

class LdapAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password cannot be empty.
     */
    public function testCheckAuthenticationWithoutCredentials()
    {
        $provider = $this->getProvider(false);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    /**
     * @expectedException        Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testRetrieveUserWithBadCredentials()
    {
        $provider = $this->getProvider(true, false);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUserOk()
    {
        $provider = $this->getProvider(true, true);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function getProvider($isLoadMethodCalled = false, $validUser = true)
    {
        $userProvider = $this
            ->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\LdapUserProvider')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        if ($isLoadMethodCalled) {
            if ($validUser) {
                $userProvider
                    ->expects($this->once())
                    ->method('loadUserByUsernameAndPassword')
                    ->will($this->returnValue(new User('foo', 'bar')))
                ;
            } else {
                $userProvider
                    ->expects($this->once())
                    ->method('loadUserByUsernameAndPassword')
                    ->will($this->throwException(new UsernameNotFoundException('baz')))
                ;
            }
        }

        $userChecker = $this->getMock('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface');

        return new LdapAuthenticationProvider($userProvider, $userChecker, 'key');
    }
}
