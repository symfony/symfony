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
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Component\Security\Http\EventListener\UserCheckerListener;

class UserCheckerListenerTest extends TestCase
{
    private $userChecker;
    private $listener;
    private $user;

    protected function setUp(): void
    {
        $this->userChecker = $this->createMock(UserCheckerInterface::class);
        $this->listener = new UserCheckerListener($this->userChecker);
        $this->user = $this->createMock(UserInterface::class);
    }

    public function testPreAuth()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user);

        $this->listener->preCredentialsVerification($this->createEvent());
    }

    public function testPreAuthNoUser()
    {
        $this->userChecker->expects($this->never())->method('checkPreAuth');

        $this->listener->preCredentialsVerification($this->createEvent(true, null));
    }

    public function testPostAuthValidCredentials()
    {
        $this->userChecker->expects($this->once())->method('checkPostAuth')->with($this->user);

        $this->listener->postCredentialsVerification($this->createEvent(true));
    }

    public function testPostAuthInvalidCredentials()
    {
        $this->userChecker->expects($this->never())->method('checkPostAuth')->with($this->user);

        $this->listener->postCredentialsVerification($this->createEvent());
    }

    public function testPostAuthNoUser()
    {
        $this->userChecker->expects($this->never())->method('checkPostAuth');

        $this->listener->postCredentialsVerification($this->createEvent(true, null));
    }

    private function createEvent($credentialsValid = false, $customUser = false)
    {
        $event = new VerifyAuthenticatorCredentialsEvent($this->createMock(AuthenticatorInterface::class), [], false === $customUser ? $this->user : $customUser);
        if ($credentialsValid) {
            $event->setCredentialsValid(true);
        }

        return $event;
    }
}
