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
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\User;
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
    private $encoderFactory;
    private $listener;
    private $user;

    protected function setUp(): void
    {
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->listener = new CheckCredentialsListener($this->encoderFactory);
        $this->user = new User('wouter', 'encoded-password');
    }

    /**
     * @dataProvider providePasswords
     */
    public function testPasswordAuthenticated($password, $passwordValid, $result)
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('isPasswordValid')->with('encoded-password', $password)->willReturn($passwordValid);

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->identicalTo($this->user))->willReturn($encoder);

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
            $this->expectExceptionMessage('The presented password is invalid.');
        }

        $credentials = new PasswordCredentials($password);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), $credentials)));

        if (true === $result) {
            $this->assertTrue($credentials->isResolved());
        }
    }

    public function providePasswords()
    {
        yield ['ThePa$$word', true, true];
        yield ['Invalid', false, false];
    }

    public function testEmptyPassword()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password cannot be empty.');

        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = $this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('')));
        $this->listener->checkPassport($event);
    }

    /**
     * @dataProvider provideCustomAuthenticatedResults
     */
    public function testCustomAuthenticated($result)
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        if (false === $result) {
            $this->expectException(BadCredentialsException::class);
        }

        $credentials = new CustomCredentials(function () use ($result) {
            return $result;
        }, ['password' => 'foo']);
        $this->listener->checkPassport($this->createEvent(new Passport(new UserBadge('wouter', function () { return $this->user; }), $credentials)));

        if (true === $result) {
            $this->assertTrue($credentials->isResolved());
        }
    }

    public function provideCustomAuthenticatedResults()
    {
        yield [true];
        yield [false];
    }

    public function testNoCredentialsBadgeProvided()
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = $this->createEvent(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $this->listener->checkPassport($event);
    }

    public function testAddsPasswordUpgradeBadge()
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('isPasswordValid')->with('encoded-password', 'ThePa$$word')->willReturn(true);

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->identicalTo($this->user))->willReturn($encoder);

        $passport = new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'));
        $this->listener->checkPassport($this->createEvent($passport));

        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $this->assertEquals('ThePa$$word', $passport->getBadge(PasswordUpgradeBadge::class)->getAndErasePlaintextPassword());
    }

    public function testAddsNoPasswordUpgradeBadgeIfItAlreadyExists()
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('isPasswordValid')->with('encoded-password', 'ThePa$$word')->willReturn(true);

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->identicalTo($this->user))->willReturn($encoder);

        $passport = $this->getMockBuilder(Passport::class)
            ->setMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    public function testAddsNoPasswordUpgradeBadgeIfPasswordIsInvalid()
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('isPasswordValid')->with('encoded-password', 'ThePa$$word')->willReturn(false);

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->identicalTo($this->user))->willReturn($encoder);

        $passport = $this->getMockBuilder(Passport::class)
            ->setMethods(['addBadge'])
            ->setConstructorArgs([new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('ThePa$$word'), [new PasswordUpgradeBadge('ThePa$$word')]])
            ->getMock();

        $passport->expects($this->never())->method('addBadge')->with($this->isInstanceOf(PasswordUpgradeBadge::class));

        $this->listener->checkPassport($this->createEvent($passport));
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }
}
