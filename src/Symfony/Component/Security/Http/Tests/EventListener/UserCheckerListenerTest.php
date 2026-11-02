<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\UserCheckerListener;
use Symfony\Component\Security\Http\Tests\Fixtures\DummyAuthenticator;

class UserCheckerListenerTest extends TestCase
{
    private MockObject&UserCheckerInterface $userChecker;
    private UserCheckerListener $listener;
    private InMemoryUser $user;

    protected function setUp(): void
    {
        $this->userChecker = $this->createMock(UserCheckerInterface::class);
        $this->listener = new UserCheckerListener($this->userChecker);
        $this->user = new InMemoryUser('test', null);
    }

    public function testPreAuth()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user);

        $this->listener->preCheckCredentials($this->createCheckPassportEvent());
    }

    public function testPreAuthenticatedBadge()
    {
        $this->userChecker->expects($this->never())->method('checkPreAuth');

        $this->listener->preCheckCredentials($this->createCheckPassportEvent(new SelfValidatingPassport(new UserBadge('test', fn () => $this->user), [new PreAuthenticatedUserBadge()])));
    }

    public function testPreAuthFailureHashesThePresentedPassword()
    {
        $exception = new DisabledException();
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user)->willThrowException($exception);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('ThePa$$word');
        $listener = new UserCheckerListener($this->userChecker, new PasswordHasherFactory([InMemoryUser::class => $hasher]));

        try {
            $listener->preCheckCredentials($this->createCheckPassportEvent(new Passport(new UserBadge('test', fn () => $this->user), new PasswordCredentials('ThePa$$word'))));
            $this->fail('An exception should have been thrown.');
        } catch (DisabledException $e) {
            $this->assertSame($exception, $e);
        }
    }

    #[DataProvider('providePassportsWithoutPasswordToHash')]
    public function testPreAuthFailureWithoutPasswordToHash(\Closure $createPassport)
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->willThrowException(new DisabledException());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hash');
        $listener = new UserCheckerListener($this->userChecker, new PasswordHasherFactory([InMemoryUser::class => $hasher]));

        $this->expectException(DisabledException::class);

        $listener->preCheckCredentials($this->createCheckPassportEvent($createPassport(new UserBadge('test', fn () => $this->user))));
    }

    public static function providePassportsWithoutPasswordToHash(): iterable
    {
        yield 'no password credentials' => [static fn (UserBadge $userBadge) => new SelfValidatingPassport($userBadge)];
        yield 'empty password' => [static fn (UserBadge $userBadge) => new Passport($userBadge, new PasswordCredentials(''))];
        yield 'resolved password' => [static function (UserBadge $userBadge) {
            $credentials = new PasswordCredentials('ThePa$$word');
            $credentials->markResolved();

            return new Passport($userBadge, $credentials);
        }];
        yield 'ldap' => [static fn (UserBadge $userBadge) => (new Passport($userBadge, new PasswordCredentials('ThePa$$word')))->addBadge(new class implements BadgeInterface {
            public function isResolved(): bool
            {
                return false;
            }
        }, LdapBadge::class)];
    }

    #[DataProvider('provideUnhashablePasswords')]
    public function testPreAuthFailureWithUnhashablePassword(?PasswordHasherFactory $hasherFactory, string $password)
    {
        $exception = new DisabledException();
        $this->userChecker->expects($this->once())->method('checkPreAuth')->willThrowException($exception);
        $listener = new UserCheckerListener($this->userChecker, $hasherFactory);

        try {
            $listener->preCheckCredentials($this->createCheckPassportEvent(new Passport(new UserBadge('test', fn () => $this->user), new PasswordCredentials($password))));
            $this->fail('An exception should have been thrown.');
        } catch (DisabledException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public static function provideUnhashablePasswords(): iterable
    {
        yield 'no hasher factory' => [null, 'ThePa$$word'];
        yield 'no hasher' => [new PasswordHasherFactory([]), 'ThePa$$word'];
        yield 'too long' => [new PasswordHasherFactory([InMemoryUser::class => ['algorithm' => 'plaintext']]), str_repeat('a', 4097)];
    }

    public function testPreAuthFailureHashesThePasswordOfAnLdapLoginWhenAskedTo()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->willThrowException(new DisabledException());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('ThePa$$word');
        $listener = new UserCheckerListener($this->userChecker, new PasswordHasherFactory([InMemoryUser::class => $hasher]), true);

        $passport = new Passport(new UserBadge('test', fn () => $this->user), new PasswordCredentials('ThePa$$word'));
        $passport->addBadge($this->createStub(BadgeInterface::class), LdapBadge::class);

        $this->expectException(DisabledException::class);

        $listener->preCheckCredentials($this->createCheckPassportEvent($passport));
    }

    public function testPreAuthSuccessDoesNotHashThePassword()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hash');
        $listener = new UserCheckerListener($this->userChecker, new PasswordHasherFactory([InMemoryUser::class => $hasher]));

        $listener->preCheckCredentials($this->createCheckPassportEvent(new Passport(new UserBadge('test', fn () => $this->user), new PasswordCredentials('ThePa$$word'))));
    }

    public function testPostAuthValidCredentials()
    {
        $this->userChecker->expects($this->once())->method('checkPostAuth')->with($this->user);

        $this->listener->postCheckCredentials(new AuthenticationSuccessEvent(new PostAuthenticationToken($this->user, 'main', [])));
    }

    public function testTokenIsPassedToPost()
    {
        $token = new PostAuthenticationToken($this->user, 'main', []);
        $this->userChecker->expects($this->once())->method('checkPostAuth')->with($this->user, $token);

        $this->listener->postCheckCredentials(new AuthenticationSuccessEvent($token));
    }

    private function createCheckPassportEvent($passport = null)
    {
        $passport ??= new SelfValidatingPassport(new UserBadge('test', fn () => $this->user));

        return new CheckPassportEvent(new DummyAuthenticator(), $passport);
    }
}
