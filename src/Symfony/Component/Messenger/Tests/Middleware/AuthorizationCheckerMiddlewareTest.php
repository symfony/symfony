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
use Symfony\Component\Messenger\Middleware\AuthorizationCheckerMiddleware;
use Symfony\Component\Messenger\Stamp\AuthorizationAttributeStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
class AuthorizationCheckerMiddlewareTest extends MiddlewareTestCase
{
    public function testAuthorizationCheckWithNoStampAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects($this->never())
            ->method('isGranted')
        ;

        (new AuthorizationCheckerMiddleware($authorizationChecker))->handle($envelope, $this->getStackMock());
    }

    public function testAuthorizationCheckWithStampAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = (new Envelope($message))->with(new AuthorizationAttributeStamp($attribute = 'foo'));

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with($attribute, $message)
            ->willReturn(true)
        ;

        (new AuthorizationCheckerMiddleware($authorizationChecker))->handle($envelope, $this->getStackMock());
    }

    public function testUnauthorizedException()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\UnauthorizedException');
        $this->expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" with attribute "foo" is unauthorized.');

        $message = new DummyMessage('Hey');
        $envelope = (new Envelope($message))->with(new AuthorizationAttributeStamp($attribute = 'foo'));

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with($attribute, $message)
            ->willReturn(false)
        ;

        (new AuthorizationCheckerMiddleware($authorizationChecker))->handle($envelope, $this->getStackMock(false));
    }
}
