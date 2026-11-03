<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class CheckLdapCredentialsListenerTest extends TestCase
{
    #[DataProvider('provideShouldNotCheckPassport')]
    public function testShouldNotCheckPassport($authenticator, $passport)
    {
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->expects($this->never())->method('bind');

        $listener = $this->createListener($ldap);
        $listener->onCheckPassport(new CheckPassportEvent($authenticator, $passport));
    }

    public static function provideShouldNotCheckPassport()
    {
        // no LdapBadge
        yield [new TestAuthenticator(), new Passport(new UserBadge('test'), new PasswordCredentials('s3cret'))];

        // ldap already resolved
        $badge = new LdapBadge('app.ldap');
        $badge->markResolved();
        yield [new TestAuthenticator(), new Passport(new UserBadge('test'), new PasswordCredentials('s3cret'), [$badge])];
    }

    public function testPasswordCredentialsAlreadyResolvedThrowsException()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LDAP authentication password verification cannot be completed because something else has already resolved the PasswordCredentials.');

        $badge = new PasswordCredentials('s3cret');
        $badge->markResolved();
        $passport = new Passport(new UserBadge('test'), $badge, [new LdapBadge('app.ldap')]);

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent(new TestAuthenticator(), $passport));
    }

    public function testInvalidLdapServiceId()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot check credentials using the "not_existing_ldap_service" ldap service, as such service is not found. Did you maybe forget to add the "ldap" service tag to this service?');

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('not_existing_ldap_service')));
    }

    #[DataProvider('provideWrongPassportData')]
    public function testWrongPassport($passport)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LDAP authentication requires a passport containing password credentials, authenticator "'.TestAuthenticator::class.'" does not fulfill these requirements.');

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent(new TestAuthenticator(), $passport));
    }

    public static function provideWrongPassportData()
    {
        // no password credentials
        yield [new SelfValidatingPassport(new UserBadge('test'), [new LdapBadge('app.ldap')])];
    }

    public function testEmptyPasswordShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password cannot be empty.');

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent(''));
    }

    public function testBindFailureShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password is invalid.');

        $ldap = $this->createStub(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->method('bind')->willThrowException(new InvalidCredentialsException());

        $listener = $this->createListener($ldap);
        $listener->onCheckPassport($this->createEvent());
    }

    public function testQueryForDn()
    {
        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['elsa', 'test1234A$'],
                    ['', 's3cr3t'],
                ];

                $this->assertSame(array_shift($series), $args);
            })
        ;
        $ldap->method('escape')->willReturnMap([
            ['Wouter', '', LdapInterface::ESCAPE_FILTER, 'wouter'],
        ]);
        $ldap->expects($this->once())->method('query')->with('{user_identifier}', 'wouter_test')->willReturn($query);

        $listener = $this->createListener($ldap);
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test')));
    }

    public function testEmptyQueryResultShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented user identifier is invalid.');

        $collection = $this->createStub(CollectionInterface::class);

        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['elsa', 'test1234A$'],
                    ['', 's3cr3t'],
                ];

                $this->assertSame(array_shift($series), $args);
            })
        ;
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->expects($this->once())->method('query')->willReturn($query);

        $listener = $this->createListener($ldap);
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test')));
    }

    public function testLdapUsersOnlySkipsTheBindForAUserFromAnotherProvider()
    {
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->expects($this->never())->method('bind');

        $event = $this->createEvent('s3cr3t', new LdapBadge('app.ldap', ldapUsersOnly: true));
        $this->createListener($ldap)->onCheckPassport($event);

        $passport = $event->getPassport();
        // resolved, so a missing listener still fails closed, but the password is left to
        // CheckCredentialsListener rather than silently accepted here
        $this->assertTrue($passport->getBadge(LdapBadge::class)->isResolved());
        $this->assertFalse($passport->getBadge(PasswordCredentials::class)->isResolved());
    }

    public function testLdapUsersOnlyStillBindsForAnLdapUser()
    {
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->expects($this->once())->method('bind')->with('Wouter', 's3cr3t');

        $event = new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(
                new UserBadge('Wouter', static fn () => new LdapUser(new Entry('Wouter'), 'Wouter', null, ['ROLE_USER'])),
                new PasswordCredentials('s3cr3t'),
                [new LdapBadge('app.ldap', ldapUsersOnly: true)]
            )
        );

        $this->createListener($ldap)->onCheckPassport($event);

        $this->assertTrue($event->getPassport()->getBadge(PasswordCredentials::class)->isResolved());
    }

    public function testLdapUsersOnlyStillRejectsAnEmptyPasswordForAnLdapUser()
    {
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->expects($this->never())->method('bind');

        $event = new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(
                new UserBadge('Wouter', static fn () => new LdapUser(new Entry('Wouter'), 'Wouter', null, ['ROLE_USER'])),
                new PasswordCredentials(''),
                [new LdapBadge('app.ldap', ldapUsersOnly: true)]
            )
        );

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password cannot be empty.');

        $this->createListener($ldap)->onCheckPassport($event);
    }

    public function testBindsAUserFromAnotherProviderWhenTheBadgeDoesNotRestrictToLdapUsers()
    {
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->expects($this->once())->method('bind')->with('Wouter', 's3cr3t');

        $event = $this->createEvent();
        $this->createListener($ldap)->onCheckPassport($event);

        $this->assertTrue($event->getPassport()->getBadge(PasswordCredentials::class)->isResolved());
    }

    private function createEvent($password = 's3cr3t', $ldapBadge = null)
    {
        return new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(new UserBadge('Wouter', static fn () => new InMemoryUser('Wouter', null, ['ROLE_USER'])), new PasswordCredentials($password), [$ldapBadge ?? new LdapBadge('app.ldap')])
        );
    }

    #[DataProvider('provideFailedLdapLogins')]
    public function testLdapUsersOnlyHashesThePasswordWhenTheLdapLoginFails(LdapBadge $ldapBadge, CollectionInterface $queryResult, string $exceptionMessage)
    {
        $query = $this->createStub(QueryInterface::class);
        $query->method('execute')->willReturn($queryResult);

        $ldap = $this->createStub(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->method('query')->willReturn($query);
        $ldap->method('bind')->willReturnCallback(static function (string $dn, string $password) {
            if ('s3cr3t' === $password) {
                throw new InvalidCredentialsException();
            }
        });

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('s3cr3t');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->createListener($ldap, $hasher)->onCheckPassport($this->createLdapUserEvent($ldapBadge));
    }

    public static function provideFailedLdapLogins(): iterable
    {
        $noEntry = new class([]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };
        $oneEntry = new class([new Entry('cn=Wouter')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        yield 'wrong password' => [new LdapBadge('app.ldap', ldapUsersOnly: true), $noEntry, 'The presented password is invalid.'];
        yield 'not found by the query' => [new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test', true), $noEntry, 'The presented user identifier is invalid.'];
        yield 'wrong password after the query' => [new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test', true), $oneEntry, 'The presented password is invalid.'];
    }

    public function testLdapUsersOnlyHashesNoPasswordWhenTheLdapLoginSucceeds()
    {
        $ldap = $this->createStub(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hash');

        $event = $this->createLdapUserEvent(new LdapBadge('app.ldap', ldapUsersOnly: true));
        $this->createListener($ldap, $hasher)->onCheckPassport($event);

        $this->assertTrue($event->getPassport()->getBadge(PasswordCredentials::class)->isResolved());
    }

    public function testHashesNoPasswordWithoutLdapUsersOnly()
    {
        $ldap = $this->createStub(LdapInterface::class);
        $ldap->method('escape')->willReturnArgument(0);
        $ldap->method('bind')->willThrowException(new InvalidCredentialsException());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hash');

        $this->expectException(BadCredentialsException::class);

        $this->createListener($ldap, $hasher)->onCheckPassport($this->createLdapUserEvent(new LdapBadge('app.ldap')));
    }

    private function createLdapUserEvent(LdapBadge $ldapBadge): CheckPassportEvent
    {
        return new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(
                new UserBadge('Wouter', static fn () => new LdapUser(new Entry('Wouter'), 'Wouter', null, ['ROLE_USER'])),
                new PasswordCredentials('s3cr3t'),
                [$ldapBadge]
            )
        );
    }

    private function createListener(?LdapInterface $ldap = null, ?PasswordHasherInterface $hasher = null)
    {
        $ldapLocator = new class(['app.ldap' => fn () => $ldap ?? $this->createStub(LdapInterface::class)]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        if (!$hasher) {
            return new CheckLdapCredentialsListener($ldapLocator);
        }

        return new CheckLdapCredentialsListener($ldapLocator, new PasswordHasherFactory([InMemoryUser::class => $hasher]), InMemoryUser::class);
    }
}

if (interface_exists(AuthenticatorInterface::class)) {
    class TestAuthenticator implements AuthenticatorInterface
    {
        public function supports(Request $request): ?bool
        {
        }

        public function authenticate(Request $request): Passport
        {
        }

        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
        {
        }

        public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
        {
        }

        public function createToken(Passport $passport, string $firewallName): TokenInterface
        {
        }
    }
}
