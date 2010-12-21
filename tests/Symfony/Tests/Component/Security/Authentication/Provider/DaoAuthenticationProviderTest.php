<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Encoder\EncoderFactory;

use Symfony\Component\Security\Encoder\PlaintextPasswordEncoder;

use Symfony\Component\Security\Authentication\Provider\DaoAuthenticationProvider;

class DaoAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationServiceException
     */
    public function testRetrieveUserWhenProviderDoesNotReturnAnAccountInterface()
    {
        $provider = $this->getProvider('fabien');
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\UsernameNotFoundException
     */
    public function testRetrieveUserWhenUsernameIsNotFound()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\UsernameNotFoundException', null, array(), '', false)))
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface'), $this->getMock('Symfony\Component\Security\Encoder\EncoderFactoryInterface'));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationServiceException
     */
    public function testRetrieveUserWhenAnExceptionOccurs()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->will($this->throwException($this->getMock('RuntimeException', null, array(), '', false)))
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface'), $this->getMock('Symfony\Component\Security\Encoder\EncoderFactoryInterface'));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserReturnsUserFromTokenOnReauthentication()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $userProvider->expects($this->never())
                     ->method('loadUserByUsername')
        ;

        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->will($this->returnValue($user))
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface'), $this->getMock('Symfony\Component\Security\Encoder\EncoderFactoryInterface'));
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($provider, null, $token);

        $this->assertSame($user, $result);
    }

    public function testRetrieveUser()
    {
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->will($this->returnValue($user))
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface'), $this->getMock('Symfony\Component\Security\Encoder\EncoderFactoryInterface'));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $this->assertSame($user, $method->invoke($provider, 'fabien', $this->getSupportedToken()));
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testCheckAuthenticationWhenCredentialsAreEmpty()
    {
        $provider = $this->getProvider();
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->will($this->returnValue(''))
        ;

        $method->invoke($provider, $this->getMock('Symfony\Component\Security\User\AccountInterface'), $token);
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testCheckAuthenticationWhenCredentialsAreNotValid()
    {
        $encoder = $this->getMock('Symfony\Component\Security\Encoder\PasswordEncoderInterface');
        $encoder->expects($this->once())
                ->method('isPasswordValid')
                ->will($this->returnValue(false))
        ;

        $provider = $this->getProvider(false, false, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->will($this->returnValue('foo'))
        ;

        $method->invoke($provider, $this->getMock('Symfony\Component\Security\User\AccountInterface'), $token);
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testCheckAuthenticationDoesNotReauthenticateWhenPasswordHasChanged()
    {
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user->expects($this->once())
             ->method('getPassword')
             ->will($this->returnValue('foo'))
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->will($this->returnValue($user));

        $dbUser = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $dbUser->expects($this->once())
               ->method('getPassword')
               ->will($this->returnValue('newFoo'))
        ;

        $provider = $this->getProvider(false, false, null);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);
        $reflection->invoke($provider, $dbUser, $token);
    }

    public function testCheckAuthenticationWhenTokenNeedsReauthenticationWorksWithoutOriginalCredentials()
    {
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user->expects($this->once())
             ->method('getPassword')
             ->will($this->returnValue('foo'))
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->will($this->returnValue($user));

        $dbUser = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $dbUser->expects($this->once())
               ->method('getPassword')
               ->will($this->returnValue('foo'))
        ;

        $provider = $this->getProvider(false, false, null);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);
        $reflection->invoke($provider, $dbUser, $token);
    }

    public function testCheckAuthentication()
    {
        $encoder = $this->getMock('Symfony\Component\Security\Encoder\PasswordEncoderInterface');
        $encoder->expects($this->once())
                ->method('isPasswordValid')
                ->will($this->returnValue(true))
        ;

        $provider = $this->getProvider(false, false, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->will($this->returnValue('foo'))
        ;

        $method->invoke($provider, $this->getMock('Symfony\Component\Security\User\AccountInterface'), $token);
    }

    protected function getSupportedToken()
    {
        return $this->getMock('Symfony\Component\Security\Authentication\Token\UsernamePasswordToken', array('getCredentials', 'getUser'), array(), '', false);
    }

    protected function getProvider($user = false, $userChecker = false, $passwordEncoder = null)
    {
        $userProvider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        if (false !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByUsername')
                         ->will($this->returnValue($user))
            ;
        }

        if (false === $userChecker) {
            $userChecker = $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface');
        }

        if (null === $passwordEncoder) {
            $passwordEncoder = new PlaintextPasswordEncoder();
        }

        $encoderFactory = $this->getMock('Symfony\Component\Security\Encoder\EncoderFactoryInterface');
        $encoderFactory
            ->expects($this->any())
            ->method('getEncoder')
            ->will($this->returnValue($passwordEncoder))
        ;

        return new DaoAuthenticationProvider($userProvider, $userChecker, $encoderFactory);
    }
}
