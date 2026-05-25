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
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CsrfProtectionListener;
use Symfony\Component\Security\Http\Tests\Fixtures\DummyAuthenticator;

class CsrfProtectionListenerTest extends TestCase
{
    public function testNoCsrfTokenBadge()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $event = $this->createEvent($this->createPassport(null));
        $listener = new CsrfProtectionListener($csrfTokenManager);
        $listener->checkPassport($event);
    }

    public function testValidCsrfToken()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('authenticator_token_id', 'abc123'))
            ->willReturn(true);

        $badge = new CsrfTokenBadge('authenticator_token_id', 'abc123');
        $event = $this->createEvent($this->createPassport($badge));
        $listener = new CsrfProtectionListener($csrfTokenManager);
        $listener->checkPassport($event);

        $this->assertTrue($badge->isResolved());
    }

    public function testInvalidCsrfToken()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('authenticator_token_id', 'abc123'))
            ->willReturn(false);

        $event = $this->createEvent($this->createPassport(new CsrfTokenBadge('authenticator_token_id', 'abc123')));

        $listener = new CsrfProtectionListener($csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $listener->checkPassport($event);
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent(new DummyAuthenticator(), $passport);
    }

    private function createPassport(?CsrfTokenBadge $badge)
    {
        $passport = new SelfValidatingPassport(new UserBadge('wouter', static fn ($username) => new InMemoryUser($username, 'pass')));
        if ($badge) {
            $passport->addBadge($badge);
        }

        return $passport;
    }
}
