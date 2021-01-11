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
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Tests\Encoder\TestPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class DaoAuthenticationProviderTest extends TestCase
{
    public function testRetrieveUserWhenProviderDoesNotReturnAnUserInterface()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationServiceException::class);
        $provider = $this->getProvider('fabien');
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenUsernameIsNotFound()
    {
        $this->expectException(UsernameNotFoundException::class);
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willThrowException(new UsernameNotFoundException())
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock(), 'key', $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenAnExceptionOccurs()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationServiceException::class);
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willThrowException(new \RuntimeException())
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock(), 'key', $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserReturnsUserFromTokenOnReauthentication()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $userProvider->expects($this->never())
                     ->method('loadUserByUsername')
        ;

        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user)
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock(), 'key', $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock());
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($provider, null, $token);

        $this->assertSame($user, $result);
    }

    public function testRetrieveUser()
    {
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willReturn($user)
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock(), 'key', $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $this->assertSame($user, $method->invoke($provider, 'fabien', $this->getSupportedToken()));
    }

    public function testCheckAuthenticationWhenCredentialsAreEmpty()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $encoder = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface::class)->getMock();
        $encoder
            ->expects($this->never())
            ->method('isPasswordValid')
        ;

        $provider = $this->getProvider(null, null, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token
            ->expects($this->once())
            ->method('getCredentials')
            ->willReturn('')
        ;

        $method->invoke(
            $provider,
            $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock(),
            $token
        );
    }

    public function testCheckAuthenticationWhenCredentialsAre0()
    {
        $encoder = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface::class)->getMock();
        $encoder
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token
            ->expects($this->once())
            ->method('getCredentials')
            ->willReturn('0')
        ;

        $method->invoke(
            $provider,
            new User('username', 'password'),
            $token
        );
    }

    public function testCheckAuthenticationWhenCredentialsAreNotValid()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $encoder = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface::class)->getMock();
        $encoder->expects($this->once())
                ->method('isPasswordValid')
                ->willReturn(false)
        ;

        $provider = $this->getProvider(null, null, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $method->invoke($provider, new User('username', 'password'), $token);
    }

    public function testCheckAuthenticationDoesNotReauthenticateWhenPasswordHasChanged()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $dbUser->expects($this->once())
               ->method('getPassword')
               ->willReturn('newFoo')
        ;

        $provider = $this->getProvider();
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);
        $reflection->invoke($provider, $dbUser, $token);
    }

    public function testCheckAuthenticationWhenTokenNeedsReauthenticationWorksWithoutOriginalCredentials()
    {
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $dbUser->expects($this->once())
               ->method('getPassword')
               ->willReturn('foo')
        ;

        $provider = $this->getProvider();
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);
        $reflection->invoke($provider, $dbUser, $token);
    }

    public function testCheckAuthentication()
    {
        $encoder = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface::class)->getMock();
        $encoder->expects($this->once())
                ->method('isPasswordValid')
                ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $encoder);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $method->invoke($provider, new User('username', 'password'), $token);
    }

    public function testPasswordUpgrades()
    {
        $user = new User('user', 'pwd');

        $encoder = $this->getMockBuilder(TestPasswordEncoderInterface::class)->getMock();
        $encoder->expects($this->once())
                ->method('isPasswordValid')
                ->willReturn(true)
        ;
        $encoder->expects($this->once())
                ->method('encodePassword')
                ->willReturn('foobar')
        ;
        $encoder->expects($this->once())
                ->method('needsRehash')
                ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $encoder);

        $userProvider = ((array) $provider)[sprintf("\0%s\0userProvider", DaoAuthenticationProvider::class)];
        $userProvider->expects($this->once())
            ->method('upgradePassword')
            ->with($user, 'foobar')
        ;

        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $method->invoke($provider, $user, $token);
    }

    protected function getSupportedToken()
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class)->setMethods(['getCredentials', 'getUser', 'getProviderKey'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        return $mock;
    }

    protected function getProvider($user = null, $userChecker = null, $passwordEncoder = null)
    {
        $userProvider = $this->getMockBuilder(PasswordUpgraderProvider::class)->getMock();
        if (null !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByUsername')
                         ->willReturn($user)
            ;
        }

        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        }

        if (null === $passwordEncoder) {
            $passwordEncoder = new PlaintextPasswordEncoder();
        }

        $encoderFactory = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock();
        $encoderFactory
            ->expects($this->any())
            ->method('getEncoder')
            ->willReturn($passwordEncoder)
        ;

        return new DaoAuthenticationProvider($userProvider, $userChecker, 'key', $encoderFactory);
    }
}

interface PasswordUpgraderProvider extends UserProviderInterface, PasswordUpgraderInterface
{
}
