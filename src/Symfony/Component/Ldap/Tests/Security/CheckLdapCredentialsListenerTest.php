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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Attribute\WithLdapPassword;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class CheckLdapCredentialsListenerTest extends TestCase
{
    use ExpectDeprecationTrait;
    private MockObject&LdapInterface $ldap;

    protected function setUp(): void
    {
        $this->ldap = $this->createMock(LdapInterface::class);
    }

    /**
     * @group legacy
     *
     * @dataProvider provideShouldCheckPassport
     */
    public function testShouldCheckPassport(AuthenticatorInterface $authenticator, Passport $passport, bool $expectedBindCalled, bool $expectDeprecation)
    {
        if ($expectDeprecation) {
            $this->expectDeprecation('Since symfony/ldap 6.4: Authenticate a user that is not an instance of Symfony\Component\Ldap\Security\LdapUser is deprecated and won\'t be the default behavior anymore in 7.0. Use the Symfony\Component\Ldap\Attribute\WithLdapPassword attribute to keep this behavior.');
        }

        if ($expectedBindCalled) {
            $this->ldap->expects($this->once())->method('bind');
        } else {
            $this->ldap->expects($this->never())->method('bind');
        }

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent($authenticator, $passport));
    }

    public static function provideShouldCheckPassport()
    {
        yield 'no LdapBadge' => [new TestAuthenticator(), new Passport(new UserBadge('test'), new PasswordCredentials('s3cret')), false, false];

        $ldapBadge = new LdapBadge('app.ldap', '{user_identifier}', '', '', null, true);
        $userBadge = new UserBadge('test');
        $userBadge->setUserLoader(function () { return new InMemoryUser('test', 'pass', ['ROLE_USER']); });
        yield 'non ldap user' => [new TestAuthenticator(), new Passport($userBadge, new PasswordCredentials('s3cret'), [$ldapBadge]), true, true];

        $userBadge = new UserBadge('test');
        $userBadge->setUserLoader(function () { return new UserWithLdapPasswordEnabled('test', ['ROLE_USER']); });
        yield 'withLdapPassword enabled' => [new TestAuthenticator(), new Passport($userBadge, new PasswordCredentials('s3cret'), [$ldapBadge]), true, false];

        $userBadge = new UserBadge('test');
        $userBadge->setUserLoader(function () { return new UserWithLdapPasswordDisabled('test', ['ROLE_USER']); });
        yield 'withLdapPassword disabled' => [new TestAuthenticator(), new Passport($userBadge, new PasswordCredentials('s3cret'), [$ldapBadge]), false, false];
    }

    public function testPasswordCredentialsAlreadyResolvedThrowsException()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LDAP authentication password verification cannot be completed because something else has already resolved the PasswordCredentials.');

        $badge = new PasswordCredentials('s3cret');
        $badge->markResolved();
        $passport = new Passport(new UserBadge('test'), $badge, [new LdapBadge('app.ldap', '{user_identifier}', '', '', null, true)]);

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent(new TestAuthenticator(), $passport));
    }

    public function testInvalidLdapServiceId()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot check credentials using the "not_existing_ldap_service" ldap service, as such service is not found. Did you maybe forget to add the "ldap" service tag to this service?');

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('not_existing_ldap_service', '{user_identifier}', '', '', null, true)));
    }

    /**
     * @dataProvider provideWrongPassportData
     */
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
        yield [new SelfValidatingPassport(
            new UserBadge('test'),
            [new LdapBadge('app.ldap', '{user_identifier}', '', '', null, true)]
        )];
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

        $this->ldap->method('escape')->willReturnArgument(0);
        $this->ldap->expects($this->any())->method('bind')->willThrowException(new InvalidCredentialsException());

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent());
    }

    /**
     * @group legacy
     *
     * @dataProvider queryForDnProvider
     */
    public function testLegacyQueryForDn(string $dnString, string $queryString)
    {
        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $this->ldap
            ->method('bind')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['elsa', 'test1234A$'],
                    ['', 's3cr3t'],
                ];

                $this->assertSame(array_shift($series), $args);
            })
        ;
        $this->ldap->expects($this->any())->method('escape')->with('Wouter', '', LdapInterface::ESCAPE_FILTER)->willReturn('wouter');
        $this->ldap->expects($this->once())->method('query')->with('{user_identifier}', 'wouter_test')->willReturn($query);

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', $dnString, 'elsa', 'test1234A$', $queryString)));
    }

    public static function queryForDnProvider(): iterable
    {
        yield ['{username}', '{username}_test'];
        yield ['{user_identifier}', '{username}_test'];
        yield ['{username}', '{user_identifier}_test'];
        yield ['{user_identifier}', '{user_identifier}_test'];
    }

    /**
     * @group legacy
     */
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

        $this->ldap
            ->method('bind')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['elsa', 'test1234A$'],
                    ['', 's3cr3t'],
                ];

                $this->assertSame(array_shift($series), $args);
            })
        ;
        $this->ldap->expects($this->any())->method('escape')->with('Wouter', '', LdapInterface::ESCAPE_FILTER)->willReturn('wouter');
        $this->ldap->expects($this->once())->method('query')->with('{user_identifier}', 'wouter_test')->willReturn($query);

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test', true)));
    }

    public function testEmptyQueryResultShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented user identifier is invalid.');

        $collection = $this->createMock(CollectionInterface::class);

        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $this->ldap
            ->method('bind')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['elsa', 'test1234A$'],
                    ['', 's3cr3t'],
                ];

                $this->assertSame(array_shift($series), $args);
            })
        ;
        $this->ldap->method('escape')->willReturnArgument(0);
        $this->ldap->expects($this->once())->method('query')->willReturn($query);

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{user_identifier}', 'elsa', 'test1234A$', '{user_identifier}_test', true)));
    }

    private function createEvent($password = 's3cr3t', $ldapBadge = null)
    {
        $ldapUser = new LdapUser(new Entry('cn=Wouter,dc=example,dc=com'), 'Wouter', null, ['ROLE_USER']);

        /*return new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(new UserBadge('Wouter', fn () => $ldapUser), new PasswordCredentials($password), [$ldapBadge ?? new LdapBadge('app.ldap', '{user_identifier}', '', '', null, true)])
        );*/

        return new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(new UserBadge('Wouter', fn () => new InMemoryUser('Wouter', null, ['ROLE_USER'])), new PasswordCredentials($password), [$ldapBadge ?? new LdapBadge('app.ldap', '{user_identifier}', '', '', null, true)])
        );
    }

    private function createListener()
    {
        $ldapLocator = new class(['app.ldap' => fn () => $this->ldap]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        return new CheckLdapCredentialsListener($ldapLocator);
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

        /**
         * @internal for compatibility with Symfony 5.4
         */
        public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
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

class BaseUser implements UserInterface
{
    public function __construct(private string $identifier, private array $roles)
    {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}

#[WithLdapPassword]
class UserWithLdapPasswordEnabled extends BaseUser {}

#[WithLdapPassword(false)]
class UserWithLdapPasswordDisabled extends BaseUser {}
