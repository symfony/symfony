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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
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

        $this->listener->onLoginSuccess($event);
    }

    public function provideUnsupportedEvents()
    {
        // no password upgrade badge
        yield [$this->createEvent(new SelfValidatingPassport($this->createMock(UserInterface::class)))];

        // blank password
        yield [$this->createEvent(new SelfValidatingPassport($this->createMock(UserInterface::class), [new PasswordUpgradeBadge('', $this->createPasswordUpgrader())]))];

        // no user
        yield [$this->createEvent($this->createMock(PassportInterface::class))];
    }

    public function testUpgrade()
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder->expects($this->any())->method('needsRehash')->willReturn(true);
        $encoder->expects($this->any())->method('encodePassword')->with('pa$$word', null)->willReturn('new-encoded-password');

        $this->encoderFactory->expects($this->any())->method('getEncoder')->with($this->user)->willReturn($encoder);

        $this->user->expects($this->any())->method('getPassword')->willReturn('old-encoded-password');

        $passwordUpgrader = $this->createPasswordUpgrader();
        $passwordUpgrader->expects($this->once())
            ->method('upgradePassword')
            ->with($this->user, 'new-encoded-password')
        ;

        $event = $this->createEvent(new SelfValidatingPassport($this->user, [new PasswordUpgradeBadge('pa$$word', $passwordUpgrader)]));
        $this->listener->onLoginSuccess($event);
    }

    private function createPasswordUpgrader()
    {
        return $this->createMock(PasswordUpgraderInterface::class);
    }

    private function createEvent(PassportInterface $passport)
    {
        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), new Request(), null, 'main');
    }
}
