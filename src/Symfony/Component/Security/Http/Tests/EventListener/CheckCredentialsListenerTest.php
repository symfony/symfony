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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CheckCredentialsListener;

class CheckCredentialsListenerTest extends TestCase
{
    private $hasherFactory;
    private $listener;
    private $user;

    protected function setUp(): void
    {
        $this->hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->listener = new CheckCredentialsListener($this->hasherFactory);
        $this->user = new InMemoryUser('wouter', 'password-hash');
    }

    /**
     * @dataProvider providePasswords
     */
    public function testPasswordAuthenticated($password, $passwordValid, $result)
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->any())->method('verify')->with('password-hash', $password)->willReturn($passwordValid);

        $this->hasherFactory->expects($this->any())->method('getPasswordHasher')->with($this->identicalTo($this->user))->willReturn($hasher);

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
            $this->expectExceptionMessage('The presented password is invalid.');
        }

        $credentials = new PasswordCredentials($password);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), $credentials)));

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
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password cannot be empty.');

        $this->hasherFactory->expects($this->never())->method('getPasswordHasher');

        $event = $this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('')));
        $this->listener->checkPassport($event);
    }

    /**
     * @dataProvider provideCustomAuthenticatedResults
     */
    public function testCustomAuthenticated($result)
    {
        $this->hasherFactory->expects($this->never())->method('getPasswordHasher');

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
        }

        $credentials = new CustomCredentials(fn () => $result, ['password' => 'foo']);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', fn () => $this->user), $credentials)));

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
        $this->hasherFactory->expects($this->never())->method('getPasswordHasher');

        $event = $this->createEvent(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $this->listener->checkPassport($event);
    }

    public function testAddsPasswordUpgradeBadge()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(true);

        $this->hasherFactory->expects($this->any())->method('getPasswordHasher')->with($this->identicalTo($this->user))->willReturn($hasher);

        $passport = new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'));
        $this->listener->checkPassport($this->createEvent($passport));

        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $this->assertEquals('ThePa$$word', $passport->getBadge(PasswordUpgradeBadge::class)->getAndErasePlaintextPassword());
    }

    public function testAddsNoPasswordUpgradeBadgeIfItAlreadyExists()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(true);

        $this->hasherFactory->expects($this->any())->method('getPasswordHasher')->with($this->identicalTo($this->user))->willReturn($hasher);

        $passport = $this->getMockBuilder(Passport::class)
            ->onlyMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    public function testAddsNoPasswordUpgradeBadgeIfPasswordIsInvalid()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(false);

        $this->hasherFactory->expects($this->any())->method('getPasswordHasher')->with($this->identicalTo($this->user))->willReturn($hasher);

        $passport = $this->getMockBuilder(Passport::class)
            ->onlyMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }
}
