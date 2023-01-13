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
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CsrfProtectionListener;

class CsrfProtectionListenerTest extends TestCase
{
    private $csrfTokenManager;
    private $listener;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->listener = new CsrfProtectionListener($this->csrfTokenManager);
    }

    public function testNoCsrfTokenBadge()
    {
        $this->csrfTokenManager->expects($this->never())->method('isTokenValid');

        $event = $this->createEvent($this->createPassport(null));
        $this->listener->checkPassport($event);
    }

    public function testValidCsrfToken()
    {
        $this->csrfTokenManager->expects($this->any())
            ->method('isTokenValid')
            ->with(new CsrfToken('authenticator_token_id', 'abc123'))
            ->willReturn(true);

        $event = $this->createEvent($this->createPassport(new CsrfTokenBadge('authenticator_token_id', 'abc123')));
        $this->listener->checkPassport($event);

        $this->expectNotToPerformAssertions();
    }

    public function testInvalidCsrfToken()
    {
        $this->expectException(InvalidCsrfTokenException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->csrfTokenManager->expects($this->any())
            ->method('isTokenValid')
            ->with(new CsrfToken('authenticator_token_id', 'abc123'))
            ->willReturn(false);

        $event = $this->createEvent($this->createPassport(new CsrfTokenBadge('authenticator_token_id', 'abc123')));
        $this->listener->checkPassport($event);
    }

    private function createEvent($passport)
    {
        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function createPassport(?CsrfTokenBadge $badge)
    {
        $passport = new SelfValidatingPassport(new UserBadge('wouter', fn ($username) => new InMemoryUser($username, 'pass')));
        if ($badge) {
            $passport->addBadge($badge);
        }

        return $passport;
    }
}
