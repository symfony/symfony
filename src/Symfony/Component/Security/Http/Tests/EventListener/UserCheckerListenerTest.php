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
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
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
        $this->user = new InMemoryUser('test', null);
    }

    public function testPreAuth()
    {
        $this->userChecker->expects($this->once())->method('checkPreAuth')->with($this->user);

        $this->listener->preCheckCredentials($this->createCheckPassportEvent());
    }

    public function testPreAuthenticatedBadge()
    {
        $this->userChecker->expects($this->never())->method('checkPreAuth');

        $this->listener->preCheckCredentials($this->createCheckPassportEvent(new SelfValidatingPassport(new UserBadge('test', fn () => $this->user), [new PreAuthenticatedUserBadge()])));
    }

    public function testPostAuthValidCredentials()
    {
        $this->userChecker->expects($this->once())->method('checkPostAuth')->with($this->user);

        $this->listener->postCheckCredentials(new AuthenticationSuccessEvent(new PostAuthenticationToken($this->user, 'main', [])));
    }

    private function createCheckPassportEvent($passport = null)
    {
        $passport ??= new SelfValidatingPassport(new UserBadge('test', fn () => $this->user));

        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function createAuthenticationSuccessEvent()
    {
        return new AuthenticationSuccessEvent(new PostAuthenticationToken($this->user, 'main', []));
    }
}
