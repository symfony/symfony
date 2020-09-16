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

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\User;
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
    private $ldap;

    protected function setUp(): void
    {
        if (!interface_exists(AuthenticatorInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-http:^5.1');
        }

        $this->ldap = $this->createMock(LdapInterface::class);
    }

    /**
     * @dataProvider provideShouldNotCheckPassport
     */
    public function testShouldNotCheckPassport($authenticator, $passport)
    {
        $this->ldap->expects($this->never())->method('bind');

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent($authenticator, $passport));
    }

    public function provideShouldNotCheckPassport()
    {
        if (!interface_exists(AuthenticatorInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-http:^5.1');
        }

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

    /**
     * @dataProvider provideWrongPassportData
     */
    public function testWrongPassport($passport)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LDAP authentication requires a passport containing a user and password credentials, authenticator "'.TestAuthenticator::class.'" does not fulfill these requirements.');

        $listener = $this->createListener();
        $listener->onCheckPassport(new CheckPassportEvent(new TestAuthenticator(), $passport));
    }

    public function provideWrongPassportData()
    {
        if (!interface_exists(AuthenticatorInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-http:^5.1');
        }

        // no password credentials
        yield [new SelfValidatingPassport(new UserBadge('test'), [new LdapBadge('app.ldap')])];

        // no user passport
        $passport = $this->createMock(PassportInterface::class);
        $passport->expects($this->any())->method('hasBadge')->with(LdapBadge::class)->willReturn(true);
        $passport->expects($this->any())->method('getBadge')->with(LdapBadge::class)->willReturn(new LdapBadge('app.ldap'));
        yield [$passport];
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

        $this->ldap->expects($this->any())->method('bind')->willThrowException(new ConnectionException());

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent());
    }

    public function testQueryForDn()
    {
        $collection = new \ArrayIterator([new Entry('')]);

        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $this->ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $this->ldap->expects($this->any())->method('escape')->with('Wouter', '', LdapInterface::ESCAPE_FILTER)->willReturn('wouter');
        $this->ldap->expects($this->once())->method('query')->with('{username}', 'wouter_test')->willReturn($query);

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{username}', 'elsa', 'test1234A$', '{username}_test')));
    }

    public function testEmptyQueryResultShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented username is invalid.');

        $collection = $this->getMockBuilder(CollectionInterface::class)->getMock();

        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query->expects($this->once())->method('execute')->willReturn($collection);

        $this->ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $this->ldap->expects($this->once())->method('query')->willReturn($query);

        $listener = $this->createListener();
        $listener->onCheckPassport($this->createEvent('s3cr3t', new LdapBadge('app.ldap', '{username}', 'elsa', 'test1234A$', '{username}_test')));
    }

    private function createEvent($password = 's3cr3t', $ldapBadge = null)
    {
        return new CheckPassportEvent(
            new TestAuthenticator(),
            new Passport(new UserBadge('Wouter', function () { return new User('Wouter', null, ['ROLE_USER']); }), new PasswordCredentials($password), [$ldapBadge ?? new LdapBadge('app.ldap')])
        );
    }

    private function createListener()
    {
        $ldapLocator = new class(['app.ldap' => function () {
            return $this->ldap;
        }]) implements ContainerInterface {
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

        public function authenticate(Request $request): PassportInterface
        {
        }

        public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
        {
        }

        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
        {
        }

        public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
        {
        }
    }
}
