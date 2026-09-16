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
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\EventListener\RefreshedUserCheckerListener;

class RefreshedUserCheckerListenerTest extends TestCase
{
    public function testTheUserIsMarkedAsChangedWhenThePreAuthCheckRejectsTheAccount()
    {
        $exception = new DisabledException();
        $event = $this->createEvent();

        (new RefreshedUserCheckerListener(new ThrowingUserChecker($exception)))($event);

        $this->assertTrue($event->isUserChanged());
        $this->assertSame($exception, $event->getException());
    }

    public function testTheUserIsMarkedAsChangedWhenThePostAuthCheckRejectsTheAccount()
    {
        $exception = new DisabledException();
        $event = $this->createEvent();

        (new RefreshedUserCheckerListener(new ThrowingUserChecker(null, $exception)))($event);

        $this->assertTrue($event->isUserChanged());
        $this->assertSame($exception, $event->getException());
    }

    public function testBothChecksRunAndTheVerdictIsLeftAloneWhenTheAccountIsAccepted()
    {
        $userChecker = new ThrowingUserChecker();
        $event = $this->createEvent();

        (new RefreshedUserCheckerListener($userChecker))($event);

        $this->assertFalse($event->isUserChanged());
        $this->assertSame(['foo'], $userChecker->preAuthCalls);
        $this->assertSame(['foo'], $userChecker->postAuthCalls);
    }

    public function testAnyAuthenticationExceptionMarksTheUserAsChanged()
    {
        // the firewall treats every AuthenticationException from a checker alike during authentication
        $exception = new CustomUserMessageAuthenticationException('Your subscription expired.');
        $event = $this->createEvent();

        (new RefreshedUserCheckerListener(new ThrowingUserChecker($exception)))($event);

        $this->assertTrue($event->isUserChanged());
        $this->assertSame($exception, $event->getException());
    }

    public function testOnlyThePostAuthCheckRunsWhenImpersonating()
    {
        $impersonated = new InMemoryUser('user', 'pass', ['ROLE_USER']);
        $impersonator = new InMemoryUser('admin', 'pass', ['ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken($impersonated, 'main', $impersonated->getRoles(), new UsernamePasswordToken($impersonator, 'main'));

        $userChecker = new ThrowingUserChecker();
        $event = new CheckRefreshedUserEvent($token, $impersonated, $impersonated);

        (new RefreshedUserCheckerListener($userChecker))($event);

        $this->assertSame([], $userChecker->preAuthCalls);
        $this->assertSame(['user'], $userChecker->postAuthCalls);
    }

    private function createEvent(): CheckRefreshedUserEvent
    {
        $user = new InMemoryUser('foo', 'bar');

        return new CheckRefreshedUserEvent(new UsernamePasswordToken($user, 'main'), $user, $user);
    }
}

class ThrowingUserChecker implements UserCheckerInterface
{
    public array $preAuthCalls = [];
    public array $postAuthCalls = [];

    public function __construct(
        private ?AuthenticationException $preAuthException = null,
        private ?AuthenticationException $postAuthException = null,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        $this->preAuthCalls[] = $user->getUserIdentifier();

        if ($this->preAuthException) {
            throw $this->preAuthException;
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->postAuthCalls[] = $user->getUserIdentifier();

        if ($this->postAuthException) {
            throw $this->postAuthException;
        }
    }
}
