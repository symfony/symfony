<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\SecurityMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SecurityMiddlewareTest extends MiddlewareTestCase
{
    public function testExecutedNextMiddlewareWhenGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with([], self::isInstanceOf(DummyMessage::class))
            ->willReturn(true);

        $middleware = new SecurityMiddleware($authorizationChecker);

        $envelope = new Envelope(new DummyMessage('he'));
        self::assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testThrowsAccessDeniedWhenAccessIsDenied(): void
    {
        $envelope = new Envelope(new DummyMessage('he'));
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with([], self::isInstanceOf(DummyMessage::class))
            ->willReturn(false);

        $middleware = new SecurityMiddleware($authorizationChecker);

        $this->expectException(AccessDeniedException::class);

        $middleware->handle($envelope, $this->getStackMock(false));
    }
}
