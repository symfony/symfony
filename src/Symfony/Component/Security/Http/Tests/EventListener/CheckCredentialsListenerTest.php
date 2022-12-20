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
        $this->hasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $this->listener = new CheckCredentialsListener($this->hasherFactory);
        $this->user = new InMemoryUser('wouter', 'password-hash');
    }

    /**
     * @dataProvider providePasswords
     */
    public function testPasswordAuthenticated($password, $passwordValid, $result)
    {
        $hasher = self::createMock(PasswordHasherInterface::class);
        $hasher->expects(self::any())->method('verify')->with('password-hash', $password)->willReturn($passwordValid);

        $this->hasherFactory->expects(self::any())->method('getPasswordHasher')->with(self::identicalTo($this->user))->willReturn($hasher);

        if (false === $result) {
            self::expectException(BadCredentialsException::class);
            self::expectExceptionMessage('The presented password is invalid.');
        }

        $credentials = new PasswordCredentials($password);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), $credentials)));

        if (true === $result) {
            self::assertTrue($credentials->isResolved());
        }
    }

    public function providePasswords()
    {
        yield ['ThePa$$word', true, true];
        yield ['Invalid', false, false];
    }

    public function testEmptyPassword()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('The presented password cannot be empty.');

        $this->hasherFactory->expects(self::never())->method('getPasswordHasher');

        $event = $this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('')));
        $this->listener->checkPassport($event);
    }

    /**
     * @dataProvider provideCustomAuthenticatedResults
     */
    public function testCustomAuthenticated($result)
    {
        $this->hasherFactory->expects(self::never())->method('getPasswordHasher');

        if (false === $result) {
            self::expectException(BadCredentialsException::class);
        }

        $credentials = new CustomCredentials(function () use ($result) {
            return $result;
        }, ['password' => 'foo']);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), $credentials)));

        if (true === $result) {
            self::assertTrue($credentials->isResolved());
        }
    }

    public function provideCustomAuthenticatedResults()
    {
        yield [true];
        yield [false];
    }

    public function testNoCredentialsBadgeProvided()
    {
        $this->hasherFactory->expects(self::never())->method('getPasswordHasher');

        $event = $this->createEvent(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $this->listener->checkPassport($event);
    }

    public function testAddsPasswordUpgradeBadge()
    {
        $hasher = self::createMock(PasswordHasherInterface::class);
        $hasher->expects(self::any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(true);

        $this->hasherFactory->expects(self::any())->method('getPasswordHasher')->with(self::identicalTo($this->user))->willReturn($hasher);

        $passport = new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'));
        $this->listener->checkPassport($this->createEvent($passport));

        self::assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        self::assertEquals('ThePa$$word', $passport->getBadge(PasswordUpgradeBadge::class)->getAndErasePlaintextPassword());
    }

    public function testAddsNoPasswordUpgradeBadgeIfItAlreadyExists()
    {
        $hasher = self::createMock(PasswordHasherInterface::class);
        $hasher->expects(self::any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(true);

        $this->hasherFactory->expects(self::any())->method('getPasswordHasher')->with(self::identicalTo($this->user))->willReturn($hasher);

        $passport = self::getMockBuilder(Passport::class)
            ->setMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects(self::never())->method('addBadge')->with(self::isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    public function testAddsNoPasswordUpgradeBadgeIfPasswordIsInvalid()
    {
        $hasher = self::createMock(PasswordHasherInterface::class);
        $hasher->expects(self::any())->method('verify')->with('password-hash', 'ThePa$$word')->willReturn(false);

        $this->hasherFactory->expects(self::any())->method('getPasswordHasher')->with(self::identicalTo($this->user))->willReturn($hasher);

        $passport = self::getMockBuilder(Passport::class)
            ->setMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects(self::never())->method('addBadge')->with(self::isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent(self::createMock(AuthenticatorInterface::class), $passport);
    }
}
