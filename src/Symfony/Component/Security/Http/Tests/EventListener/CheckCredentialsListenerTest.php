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
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CheckCredentialsListener;
use Symfony\Component\Security\Http\Tests\Fixtures\DummyAuthenticator;

class CheckCredentialsListenerTest extends TestCase
{
    private InMemoryUser $user;

    protected function setUp(): void
    {
        $this->user = new InMemoryUser('wouter', 'password-hash');
    }

    #[DataProvider('providePasswords')]
    public function testPasswordAuthenticated(string $password, bool $passwordValid, bool $result)
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('verify')->with('password-hash', $password)->willReturn($passwordValid);

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => $hasher,
        ]);

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
            $this->expectExceptionMessage('The presented password is invalid.');
        }

        $credentials = new PasswordCredentials($password);
        (new CheckCredentialsListener($hasherFactory))->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), $credentials)));

        if (true === $result) {
            $this->assertTrue($credentials->isResolved());
        }
    }

    public static function providePasswords()
    {
        yield ['ThePa$$word', true, true];
        yield ['Invalid', false, false];
    }

    public function testEmptyPassword()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactory::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $event = $this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('')));

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password cannot be empty.');

        (new CheckCredentialsListener($hasherFactory))->checkPassport($event);
    }

    #[DataProvider('provideCustomAuthenticatedResults')]
    public function testCustomAuthenticated(bool $result)
    {
        $hasherFactory = $this->createMock(PasswordHasherFactory::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
        }

        $credentials = new CustomCredentials(static fn () => $result, ['password' => 'foo']);
        (new CheckCredentialsListener($hasherFactory))->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), $credentials)));

        if (true === $result) {
            $this->assertTrue($credentials->isResolved());
        }
    }

    public static function provideCustomAuthenticatedResults()
    {
        yield [true];
        yield [false];
    }

    public function testNoCredentialsBadgeProvided()
    {
        $hasherFactory = $this->createMock(PasswordHasherFactory::class);
        $hasherFactory->expects($this->never())->method('getPasswordHasher');

        $event = $this->createEvent(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        (new CheckCredentialsListener($hasherFactory))->checkPassport($event);
    }

    public function testAddsPasswordUpgradeBadge()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(true);

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => $hasher,
        ]);

        $passport = new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'));
        (new CheckCredentialsListener($hasherFactory))->checkPassport($this->createEvent($passport));

        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $this->assertEquals('ThePa$$word', $passport->getBadge(PasswordUpgradeBadge::class)->getAndErasePlaintextPassword());
    }

    public function testAddsNoPasswordUpgradeBadgeIfItAlreadyExists()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('verify');

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => $hasher,
        ]);

        $passport = $this->getMockBuilder(Passport::class)
            ->onlyMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        (new CheckCredentialsListener($hasherFactory))->checkPassport($this->createEvent($passport));
    }

    public function testAddsNoPasswordUpgradeBadgeIfPasswordIsInvalid()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->never())->method('verify');

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => $hasher,
        ]);

        $passport = $this->getMockBuilder(Passport::class)
            ->onlyMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        (new CheckCredentialsListener($hasherFactory))->checkPassport($this->createEvent($passport));
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent(new DummyAuthenticator(), $passport);
    }
}
