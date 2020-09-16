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
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
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
        $this->user = new User('test', null);
    }

    public function testPreAuth()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user);

        $this->listener->preCheckCredentials($this->createCheckPassportEvent());
    }

    public function testPreAuthNoUser()
    {
        $this->userChecker->expects($this->never())->method('checkPreAuth');

        $this->listener->preCheckCredentials($this->createCheckPassportEvent($this->createMock(PassportInterface::class)));
    }

    public function testPreAuthenticatedBadge()
    {
        $this->userChecker->expects($this->never())->method('checkPreAuth');

        $this->listener->preCheckCredentials($this->createCheckPassportEvent(new SelfValidatingPassport(new UserBadge('test', function () { return $this->user; }), [new PreAuthenticatedUserBadge()])));
    }

    public function testPostAuthValidCredentials()
    {
        $this->userChecker->expects($this->once())->method('checkPostAuth')->with($this->user);

        $this->listener->postCheckCredentials($this->createLoginSuccessEvent());
    }

    public function testPostAuthNoUser()
    {
        $this->userChecker->expects($this->never())->method('checkPostAuth');

        $this->listener->postCheckCredentials($this->createLoginSuccessEvent($this->createMock(PassportInterface::class)));
    }

    private function createCheckPassportEvent($passport = null)
    {
        if (null === $passport) {
            $passport = new SelfValidatingPassport(new UserBadge('test', function () { return $this->user; }));
        }

        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function createLoginSuccessEvent($passport = null)
    {
        if (null === $passport) {
            $passport = new SelfValidatingPassport(new UserBadge('test', function () { return $this->user; }));
        }

        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), new Request(), null, 'main');
    }
}
