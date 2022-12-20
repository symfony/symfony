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
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\Middleware\ValidationMiddleware;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationMiddlewareTest extends MiddlewareTestCase
{
    public function testValidateAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $validator = self::createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($message)
            ->willReturn(self::createMock(ConstraintViolationListInterface::class))
        ;

        (new ValidationMiddleware($validator))->handle($envelope, $this->getStackMock());
    }

    public function testValidateWithStampAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = (new Envelope($message))->with(new ValidationStamp($groups = ['Default', 'Extra']));
        $validator = self::createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($message, null, $groups)
            ->willReturn(self::createMock(ConstraintViolationListInterface::class))
        ;

        (new ValidationMiddleware($validator))->handle($envelope, $this->getStackMock());
    }

    public function testValidationFailedException()
    {
        self::expectException(ValidationFailedException::class);
        self::expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" failed validation.');
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $violationList = self::createMock(ConstraintViolationListInterface::class);
        $violationList
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $validator = self::createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($message)
            ->willReturn($violationList)
        ;

        (new ValidationMiddleware($validator))->handle($envelope, $this->getStackMock(false));
    }
}
