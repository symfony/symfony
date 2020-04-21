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
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Component\Security\Http\EventListener\VerifyAuthenticatorCredentialsListener;

class VerifyAuthenticatorCredentialsListenerTest extends TestCase
{
    private $encoderFactory;
    private $listener;
    private $user;

    protected function setUp(): void
    {
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->listener = new VerifyAuthenticatorCredentialsListener($this->encoderFactory);
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
        $this->listener->onAuthenticating($this->createEvent(new Passport($this->user, $credentials)));

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

        $event = $this->createEvent(new Passport($this->user, new PasswordCredentials('')));
        $this->listener->onAuthenticating($event);
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
        $this->listener->onAuthenticating($this->createEvent(new Passport($this->user, $credentials)));

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

        $event = $this->createEvent(new SelfValidatingPassport($this->user));
        $this->listener->onAuthenticating($event);
    }

    private function createEvent($passport)
    {
        return new VerifyAuthenticatorCredentialsEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }
}
