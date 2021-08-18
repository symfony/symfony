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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @group legacy
 */
class DaoAuthenticationProviderTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testRetrieveUserWhenProviderDoesNotReturnAnUserInterface()
    {
        $this->expectException(AuthenticationServiceException::class);
        $userProvider = $this->createMock(DaoAuthenticationProviderTest_UserProvider::class);
        $userProvider->expects($this->once())
                     ->method('loadUserByUsername')
                     ->willReturn('fabien')
        ;
        $provider = $this->getProvider(null, null, null, $userProvider);
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $this->expectDeprecation('Since symfony/security-core 5.3: Not implementing method "loadUserByIdentifier()" in user provider "'.get_debug_type($userProvider).'" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.');

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenUsernameIsNotFoundWithLegacyEncoderFactory()
    {
        $this->expectException(UserNotFoundException::class);
        $userProvider = new InMemoryUserProvider();

        $provider = new DaoAuthenticationProvider($userProvider, $this->createMock(UserCheckerInterface::class), 'key', $this->createMock(EncoderFactoryInterface::class));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenUsernameIsNotFound()
    {
        $this->expectException(UserNotFoundException::class);
        $userProvider = new InMemoryUserProvider();

        $provider = new DaoAuthenticationProvider($userProvider, $this->createMock(UserCheckerInterface::class), 'key', $this->createMock(PasswordHasherFactoryInterface::class));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserWhenAnExceptionOccurs()
    {
        $this->expectException(AuthenticationServiceException::class);
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider->expects($this->once())
                     ->method('loadUserByIdentifier')
                     ->willThrowException(new \RuntimeException())
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->createMock(UserCheckerInterface::class), 'key', $this->createMock(PasswordHasherFactoryInterface::class));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $method->invoke($provider, 'fabien', $this->getSupportedToken());
    }

    public function testRetrieveUserReturnsUserFromTokenOnReauthentication()
    {
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider->expects($this->never())
                     ->method('loadUserByIdentifier')
        ;

        $user = new TestUser();
        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user)
        ;

        $provider = new DaoAuthenticationProvider($userProvider, $this->createMock(UserCheckerInterface::class), 'key', $this->createMock(PasswordHasherFactoryInterface::class));
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($provider, 'someUser', $token);

        $this->assertSame($user, $result);
    }

    public function testRetrieveUser()
    {
        $userProvider = new InMemoryUserProvider(['fabien' => []]);

        $provider = new DaoAuthenticationProvider($userProvider, $this->createMock(UserCheckerInterface::class), 'key', $this->createMock(PasswordHasherFactoryInterface::class));
        $method = new \ReflectionMethod($provider, 'retrieveUser');
        $method->setAccessible(true);

        $this->assertEquals('fabien', $method->invoke($provider, 'fabien', $this->getSupportedToken())->getUserIdentifier());
    }

    public function testCheckAuthenticationWhenCredentialsAreEmpty()
    {
        $this->expectException(BadCredentialsException::class);
        $hasher = $this->getMockBuilder(PasswordHasherInterface::class)->getMock();
        $hasher
            ->expects($this->never())
            ->method('verify')
        ;

        $provider = $this->getProvider(null, null, $hasher);
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
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher
            ->expects($this->once())
            ->method('verify')
            ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $hasher);
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
            new InMemoryUser('username', 'password'),
            $token
        );
    }

    public function testCheckAuthenticationWhenCredentialsAreNotValid()
    {
        $this->expectException(BadCredentialsException::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())
                ->method('verify')
                ->willReturn(false)
        ;

        $provider = $this->getProvider(null, null, $hasher);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $method->invoke($provider, new InMemoryUser('username', 'password'), $token);
    }

    public function testCheckAuthenticationDoesNotReauthenticateWhenPasswordHasChanged()
    {
        $this->expectException(BadCredentialsException::class);
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->createMock(UserInterface::class);
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
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
             ->method('getPassword')
             ->willReturn('foo')
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getUser')
              ->willReturn($user);

        $dbUser = $this->createMock(UserInterface::class);
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
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())
                ->method('verify')
                ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $hasher);
        $method = new \ReflectionMethod($provider, 'checkAuthentication');
        $method->setAccessible(true);

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $method->invoke($provider, new InMemoryUser('username', 'password'), $token);
    }

    public function testPasswordUpgrades()
    {
        $user = new InMemoryUser('user', 'pwd');

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())
                ->method('verify')
                ->willReturn(true)
        ;
        $hasher->expects($this->once())
                ->method('hash')
                ->willReturn('foobar')
        ;
        $hasher->expects($this->once())
                ->method('needsRehash')
                ->willReturn(true)
        ;

        $provider = $this->getProvider(null, null, $hasher);

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
        $mock = $this->getMockBuilder(UsernamePasswordToken::class)->setMethods(['getCredentials', 'getUser', 'getProviderKey'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        return $mock;
    }

    protected function getProvider($user = null, $userChecker = null, $passwordHasher = null, $userProvider = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->createMock(PasswordUpgraderProvider::class);
            if (null !== $user) {
                $userProvider->expects($this->once())
                             ->method('loadUserByIdentifier')
                             ->willReturn($user)
                ;
            }
        }

        if (null === $userChecker) {
            $userChecker = $this->createMock(UserCheckerInterface::class);
        }

        if (null === $passwordHasher) {
            $passwordHasher = new PlaintextPasswordHasher();
        }

        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory
            ->expects($this->any())
            ->method('getPasswordHasher')
            ->willReturn($passwordHasher)
        ;

        return new DaoAuthenticationProvider($userProvider, $userChecker, 'key', $hasherFactory);
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

    public function getUserIdentifier(): string
    {
        return 'jane_doe';
    }

    public function eraseCredentials()
    {
    }
}
interface PasswordUpgraderProvider extends UserProviderInterface, PasswordUpgraderInterface
{
    public function upgradePassword($user, string $newHashedPassword): void;

    public function loadUserByIdentifier(string $identifier): UserInterface;
}

interface DaoAuthenticationProviderTest_UserProvider extends UserProviderInterface
{
    public function loadUserByUsername($username): string;
}
