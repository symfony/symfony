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
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\CustomAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\TokenAuthenticatedInterface;
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
        $this->user = $this->createMock(UserInterface::class);
    }

    /**
     * @dataProvider providePasswords
     */
    public function testPasswordAuthenticated($password, $passwordValid, $result)
    {
        $this->user->expects($this->any())->method('getPassword')->willReturn('encoded-password');

        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('isPasswordValid')->with('encoded-password', $password)->willReturn($passwordValid);

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->identicalTo($this->user))->willReturn($encoder);

        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator('password', $password), ['password' => $password], $this->user);
        $this->listener->onAuthenticating($event);
        $this->assertEquals($result, $event->areCredentialsValid());
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

        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator('password', ''), ['password' => ''], $this->user);
        $this->listener->onAuthenticating($event);
    }

    public function testTokenAuthenticated()
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator('token', 'some_token'), ['token' => 'abc'], $this->user);
        $this->listener->onAuthenticating($event);

        $this->assertTrue($event->areCredentialsValid());
    }

    public function testTokenAuthenticatedReturningNull()
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator('token', null), ['token' => 'abc'], $this->user);
        $this->listener->onAuthenticating($event);

        $this->assertFalse($event->areCredentialsValid());
    }

    /**
     * @dataProvider provideCustomAuthenticatedResults
     */
    public function testCustomAuthenticated($result)
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator('custom', $result), [], $this->user);
        $this->listener->onAuthenticating($event);

        $this->assertEquals($result, $event->areCredentialsValid());
    }

    public function provideCustomAuthenticatedResults()
    {
        yield [true];
        yield [false];
    }

    public function testAlreadyAuthenticated()
    {
        $event = new VerifyAuthenticatorCredentialsEvent($this->createAuthenticator(), [], $this->user);
        $event->setCredentialsValid(true);
        $this->listener->onAuthenticating($event);

        $this->assertTrue($event->areCredentialsValid());
    }

    public function testNoAuthenticatedInterfaceImplemented()
    {
        $authenticator = $this->createAuthenticator();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Authenticator %s does not have valid credentials. Authenticators must implement one of the authenticated interfaces (%s, %s or %s).', \get_class($authenticator), PasswordAuthenticatedInterface::class, TokenAuthenticatedInterface::class, CustomAuthenticatedInterface::class));

        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $event = new VerifyAuthenticatorCredentialsEvent($authenticator, [], $this->user);
        $this->listener->onAuthenticating($event);
    }

    /**
     * @return AuthenticatorInterface
     */
    private function createAuthenticator(?string $type = null, $result = null)
    {
        $interfaces = [AuthenticatorInterface::class];
        switch ($type) {
            case 'password':
                $interfaces[] = PasswordAuthenticatedInterface::class;
                break;
            case 'token':
                $interfaces[] = TokenAuthenticatedInterface::class;
                break;
            case 'custom':
                $interfaces[] = CustomAuthenticatedInterface::class;
                break;
        }

        $authenticator = $this->createMock(1 === \count($interfaces) ? $interfaces[0] : $interfaces);
        switch ($type) {
            case 'password':
                $authenticator->expects($this->any())->method('getPassword')->willReturn($result);
                break;
            case 'token':
                $authenticator->expects($this->any())->method('getToken')->willReturn($result);
                break;
            case 'custom':
                $authenticator->expects($this->any())->method('checkCredentials')->willReturn($result);
                break;
        }

        return $authenticator;
    }
}
