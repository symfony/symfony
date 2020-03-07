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
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Component\Security\Http\EventListener\PasswordMigratingListener;

class PasswordMigratingListenerTest extends TestCase
{
    private $encoderFactory;
    private $listener;
    private $user;

    protected function setUp(): void
    {
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->listener = new PasswordMigratingListener($this->encoderFactory);
        $this->user = $this->createMock(UserInterface::class);
    }

    /**
     * @dataProvider provideUnsupportedEvents
     */
    public function testUnsupportedEvents($event)
    {
        $this->encoderFactory->expects($this->never())->method('getEncoder');

        $this->listener->onCredentialsVerification($event);
    }

    public function provideUnsupportedEvents()
    {
        // unsupported authenticators
        yield [$this->createEvent($this->createMock(AuthenticatorInterface::class), $this->user)];
        yield [$this->createEvent($this->createMock([AuthenticatorInterface::class, PasswordAuthenticatedInterface::class]), $this->user)];

        // null password
        yield [$this->createEvent($this->createAuthenticator(null), $this->user)];

        // no user
        yield [$this->createEvent($this->createAuthenticator('pa$$word'), null)];

        // invalid password
        yield [$this->createEvent($this->createAuthenticator('pa$$word'), $this->user, false)];
    }

    public function testUpgrade()
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('needsRehash')->willReturn(true);
        $encoder->expects($this->any())->method('encodePassword')->with('pa$$word', null)->willReturn('new-encoded-password');

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->user)->willReturn($encoder);

        $this->user->expects($this->any())->method('getPassword')->willReturn('old-encoded-password');

        $authenticator = $this->createAuthenticator('pa$$word');
        $authenticator->expects($this->once())
            ->method('upgradePassword')
            ->with($this->user, 'new-encoded-password')
        ;

        $event = $this->createEvent($authenticator, $this->user);
        $this->listener->onCredentialsVerification($event);
    }

    /**
     * @return AuthenticatorInterface
     */
    private function createAuthenticator($password)
    {
        $authenticator = $this->createMock([AuthenticatorInterface::class, PasswordAuthenticatedInterface::class, PasswordUpgraderInterface::class]);
        $authenticator->expects($this->any())->method('getPassword')->willReturn($password);

        return $authenticator;
    }

    private function createEvent($authenticator, $user, $credentialsValid = true)
    {
        $event = new VerifyAuthenticatorCredentialsEvent($authenticator, [], $user);
        $event->setCredentialsValid($credentialsValid);

        return $event;
    }
}
