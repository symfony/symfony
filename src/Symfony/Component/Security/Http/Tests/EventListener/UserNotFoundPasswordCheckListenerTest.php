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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserChecker;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\UserCheckerListener;
use Symfony\Component\Security\Http\EventListener\UserNotFoundPasswordCheckListener;
use Symfony\Component\Security\Http\Tests\Fixtures\DummyAuthenticator;

class UserNotFoundPasswordCheckListenerTest extends TestCase
{
    public function testHashesThePresentedPasswordWhenTheUserIsNotFound()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('ThePa$$word')->willReturn('password-hash');
        $hasher->expects($this->never())->method('verify');

        $listener = new UserNotFoundPasswordCheckListener(new PasswordHasherFactory([InMemoryUser::class => $hasher]), InMemoryUser::class);
        $exception = new UserNotFoundException();

        try {
            $listener->checkPassport($this->createEvent(new Passport(new UserBadge('unknown', static fn () => throw $exception), new PasswordCredentials('ThePa$$word'))));
            $this->fail('The listener should rethrow the exception of the user loader.');
        } catch (UserNotFoundException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testHashesThePasswordOfEachLogin()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->exactly(2))->method('hash')->willReturn('password-hash');
        $hasher->expects($this->never())->method('verify');

        $listener = new UserNotFoundPasswordCheckListener(new PasswordHasherFactory([InMemoryUser::class => $hasher]), InMemoryUser::class);

        for ($i = 0; $i < 2; ++$i) {
            try {
                $listener->checkPassport($this->createEvent(new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'))));
                $this->fail('The listener should rethrow the exception of the user loader.');
            } catch (UserNotFoundException) {
            }
        }
    }

    public function testHashesOnlyWithTheBestHasherOfAMigratingHasher()
    {
        $bestHasher = $this->createMock(PasswordHasherInterface::class);
        $bestHasher->expects($this->once())->method('hash')->willReturn('password-hash');
        $bestHasher->expects($this->never())->method('verify');

        $legacyHasher = $this->createMock(PasswordHasherInterface::class);
        $legacyHasher->expects($this->never())->method('hash');
        $legacyHasher->expects($this->never())->method('verify');

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => ['instance' => $bestHasher, 'migrate_from' => ['legacy']],
            'legacy' => $legacyHasher,
        ]);
        $listener = new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class);

        $this->expectException(UserNotFoundException::class);

        $listener->checkPassport($this->createEvent(new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'))));
    }

    public function testIgnoresPasswordsTooLongToBeHashed()
    {
        $hasherFactory = new PasswordHasherFactory([InMemoryUser::class => new NativePasswordHasher(null, null, 4)]);
        $listener = new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class);

        $this->expectException(UserNotFoundException::class);

        $listener->checkPassport($this->createEvent(new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials(str_repeat('a', 4097)))));
    }

    public function testRunsBeforeTheUserChecker()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->willReturn('password-hash');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new UserCheckerListener(new InMemoryUserChecker()));
        $dispatcher->addSubscriber(new UserNotFoundPasswordCheckListener(new PasswordHasherFactory([InMemoryUser::class => $hasher]), InMemoryUser::class));

        $this->expectException(UserNotFoundException::class);

        $dispatcher->dispatch($this->createEvent(new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'))));
    }

    public function testDoesNothingWhenTheUserIsFound()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $user = new InMemoryUser('wouter', 'password-hash');
        $passport = new Passport(new UserBadge('wouter', static fn () => $user), new PasswordCredentials('ThePa$$word'));

        (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent($passport));

        $this->assertSame($user, $passport->getUser());
    }

    public function testDoesNothingWhenNoHasherIsConfigured()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->once())->method('getPasswordHasher')->with(InMemoryUser::class)->willThrowException(new \RuntimeException('No password hasher has been configured.'));

        $listener = new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class);

        for ($i = 0; $i < 2; ++$i) {
            try {
                $listener->checkPassport($this->createEvent(new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'))));
                $this->fail('The listener should rethrow the exception of the user loader.');
            } catch (UserNotFoundException) {
            }
        }
    }

    public function testLetsOtherExceptionsPropagate()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $exception = new AuthenticationServiceException();

        try {
            (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', static fn () => throw $exception), new PasswordCredentials('ThePa$$word'))));
            $this->fail('The listener should let the exception of the user loader propagate.');
        } catch (AuthenticationServiceException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testDoesNothingWithoutPasswordCredentials()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $passport = new SelfValidatingPassport(new UserBadge('unknown', static fn () => null));

        (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent($passport));
    }

    public function testDoesNothingWithResolvedPasswordCredentials()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $credentials = new PasswordCredentials('ThePa$$word');
        $credentials->markResolved();
        $passport = new Passport(new UserBadge('unknown', static fn () => null), $credentials);

        (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent($passport));
    }

    public function testDoesNothingWithAnEmptyPassword()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $passport = new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials(''));

        (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent($passport));
    }

    public function testDoesNothingWithAnLdapBadge()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $passport = new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'));
        // the badge is registered under the name of the LDAP badge so that the test doesn't need symfony/ldap
        $passport->addBadge($this->createStub(BadgeInterface::class), LdapBadge::class);

        (new UserNotFoundPasswordCheckListener($hasherFactory, InMemoryUser::class))->checkPassport($this->createEvent($passport));
    }

    public function testHashesThePasswordOfAnLdapLoginWhenAskedTo()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('ThePa$$word');

        $passport = new Passport(new UserBadge('unknown', static fn () => null), new PasswordCredentials('ThePa$$word'));
        $passport->addBadge($this->createStub(BadgeInterface::class), LdapBadge::class);

        $this->expectException(UserNotFoundException::class);

        (new UserNotFoundPasswordCheckListener(new PasswordHasherFactory([InMemoryUser::class => $hasher]), InMemoryUser::class, true))->checkPassport($this->createEvent($passport));
    }

    private function createEvent(Passport $passport): CheckPassportEvent
    {
        return new CheckPassportEvent(new DummyAuthenticator(), $passport);
    }
}
