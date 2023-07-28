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
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Attribute\WithLdapPassword;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapAuthenticator;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\UserProviderListener;
use Symfony\Component\Security\Http\HttpUtils;

class ChainProviderWithLdapTest extends TestCase
{
    public function provideChainWithLdap(): array
    {
        return [
            'user from custom provider' => ['foo', 'foopass'],
            'user from ldap provider' => ['bar', 'barpass'],
        ];
    }

    /**
     * @dataProvider provideChainWithLdap
     */
    public function testChainWithLdap(string $userIdentifier, string $pass)
    {
        $customUserProvider = new CustomUserProvider();

        $ldapAdapteur = $this->createMock(AdapterInterface::class);
        $ldapAdapteur
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionInterface::class))
        ;

        $connection
            ->method('bind')
            ->willReturnCallback(static function (?string $user, ?string $pass): void {
                if ('admin' === $user && 'adminpass' === $pass) {
                    return;
                }

                if ('bar' === $user && 'barpass' === $pass) {
                    return;
                }

                throw new ConnectionException('failure when binding');
            })
        ;

        $ldapAdapteur
            ->method('escape')
            ->willReturnArgument(0)
        ;

        $ldapAdapteur
            ->method('createQuery')
            ->willReturn($query = $this->createMock(QueryInterface::class))
        ;

        $query
            ->method('execute')
            ->willReturn($collection = $this->createMock(CollectionInterface::class));

        $collection
            ->method('count')
            ->willReturn(1)
        ;

        $collection
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('cn=bar,dc=example,dc=com', ['sAMAccountName' => ['bar'], 'userPassword' => ['barpass']]))
        ;

        $ldapProvider = new LdapUserProvider($ldap = new Ldap($ldapAdapteur), 'dc=example,dc=com', 'admin', 'adminpass', [], null, null, 'userPassword');

        $chainUserProvider = new ChainUserProvider([$customUserProvider, $ldapProvider]);

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils
            ->method('checkRequestPath')
            ->willReturn(true)
        ;

        $failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);
        $failureHandler
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $formLoginAuthenticator = new FormLoginAuthenticator(
            $httpUtils,
            $chainUserProvider,
            $this->createMock(AuthenticationSuccessHandlerInterface::class),
            $failureHandler,
            []
        );

        $ldapAuthenticator = new LdapAuthenticator($formLoginAuthenticator, 'ldap-id');

        $ldapLocator = new class($ldap) implements ContainerInterface {
            private $ldap;

            public function __construct(Ldap $ldap)
            {
                $this->ldap = $ldap;
            }

            public function get(string $id): Ldap
            {
                return $this->ldap;
            }

            public function has(string $id): bool
            {
                return 'ldap-id' === $id;
            }
        };

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CheckPassportEvent::class, [new UserProviderListener($chainUserProvider), 'checkPassport']);
        $eventDispatcher->addListener(CheckPassportEvent::class, [new CheckLdapCredentialsListener($ldapLocator), 'onCheckPassport']);
        $eventDispatcher->addListener(CheckPassportEvent::class, function (CheckPassportEvent $event): void {
            $passport = $event->getPassport();
            $userBadge = $passport->getBadge(UserBadge::class);
            if (null === $userBadge || null === $userBadge->getUser()) {
                return;
            }
            $credentials = $passport->getBadge(PasswordCredentials::class);
            if ($credentials->isResolved()) {
                return;
            }

            if ($credentials && 'foopass' === $credentials->getPassword()) {
                $credentials->markResolved();
            }
        });

        $authenticatorManager = new AuthenticatorManager(
            [$ldapAuthenticator],
            $tokenStorage = new TokenStorage(),
            $eventDispatcher,
            'main'
        );

        $request = Request::create('/login', 'POST', ['_username' => $userIdentifier, '_password' => $pass]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->assertTrue($authenticatorManager->supports($request));
        $authenticatorManager->authenticateRequest($request);

        $this->assertInstanceOf(UsernamePasswordToken::class, $token = $tokenStorage->getToken());
        $this->assertSame($userIdentifier, $token->getUserIdentifier());
    }
}

#[WithLdapPassword(false)]
class FooUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function getPassword(): ?string
    {
        return 'foopass';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'foo';
    }
}

class CustomUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === FooUser::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
       if ($identifier !== 'foo') {
           throw new UserNotFoundException('User foo not found');
       }

       return new FooUser();
    }
}
