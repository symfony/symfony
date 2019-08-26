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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class DaoAuthenticationProviderTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testRetrieveUserWhenProviderDoesNotReturnAnUserInterface()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationServiceException');
        $provider = $this->getProvider('fabien');
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenUsernameIsNotFound()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\UsernameNotFoundException');
        $userProvider = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserProviderInterface')->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willThrowException(new UsernameNotFoundException())
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface')->getMock(), 'key', $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\EncoderFactoryInterface')->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenAnExceptionOccurs()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationServiceException');
        $userProvider = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserProviderInterface')->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willThrowException(new \RuntimeException())
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface')->getMock(), 'key', $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\EncoderFactoryInterface')->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserReturnsUserFromTokenOnReauthentication()
    {
        $userProvider = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserProviderInterface')->getMock();
        $userProvider->expects($this->never())
                     ->method('loadUserByUsername')
        ;

        $user = new TestUser();
        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user)
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface')->getMock(), 'key', $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\EncoderFactoryInterface')->getMock());
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($provider, 'someUser', $token);

        $this->assertSame($user, $result);
    }

    public function testRetrieveUser()
    {
        $user = new TestUser();

        $userProvider = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserProviderInterface')->getMock();
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willReturn($user)
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface')->getMock(), 'key', $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\EncoderFactoryInterface')->getMock());
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $this->assertSame($user, $method->invoke($provider, 'fabien', $this->getSupportedToken()));
    }

    public function testCheckAuthenticationWhenCredentialsAreEmpty()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $encoder = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\PasswordEncoderInterface')->getMock();
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

        $method->invoke($provider, new TestUser(), $token);
    }

    public function testCheckAuthenticationWhenCredentialsAre0()
    {
        $encoder = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\PasswordEncoderInterface')->getMock();
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

        $method->invoke($provider, new TestUser(), $token);
    }

    public function testCheckAuthenticationWhenCredentialsAreNotValid()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $encoder = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\PasswordEncoderInterface')->getMock();
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

        $method->invoke($provider, new TestUser(), $token);
    }

    public function testCheckAuthenticationDoesNotReauthenticateWhenPasswordHasChanged()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $user = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserInterface')->getMock();
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserInterface')->getMock();
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
        $user = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserInterface')->getMock();
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserInterface')->getMock();
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
        $encoder = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\PasswordEncoderInterface')->getMock();
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

        $method->invoke($provider, new TestUser(), $token);
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
        $mock = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Authentication\\Token\\UsernamePasswordToken')->setMethods(['getCredentials', 'getUser', 'getProviderKey'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        return $mock;
    }

    protected function getProvider($user = null, $userChecker = null, $passwordEncoder = null)
    {
        $userProvider = $this->getMockBuilder([UserProviderInterface::class, PasswordUpgraderInterface::class])->getMock();
        if (null !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByUsername')
                         ->willReturn($user)
            ;
        }

        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\User\\UserCheckerInterface')->getMock();
        }

        if (null === $passwordEncoder) {
            $passwordEncoder = new PlaintextPasswordEncoder();
        }

        $encoderFactory = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\Encoder\\EncoderFactoryInterface')->getMock();
        $encoderFactory
            ->expects($this->any())
            ->method('getEncoder')
            ->willReturn($passwordEncoder)
        ;

        return new DaoAuthenticationProvider($userProvider, $userChecker, 'key', $encoderFactory);
    }
}

class TestUser implements UserInterface
{
    public function getRoles(): array
    {
        return [];
    }

    public function getPassword(): ?string
    {
        return 'secret';
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getUsername(): string
    {
        return 'jane_doe';
    }

    public function eraseCredentials()
    {
    }
}
