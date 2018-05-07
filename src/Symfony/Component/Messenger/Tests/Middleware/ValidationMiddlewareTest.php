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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Middleware\ValidationMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationMiddlewareTest extends TestCase
{
    public function testValidateAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');

        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($message)
            ->willReturn($this->createMock(ConstraintViolationListInterface::class))
        ;
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($message)
            ->willReturn('Hello')
        ;

        $result = (new ValidationMiddleware($validator))->handle($message, $next);

        $this->assertSame('Hello', $result);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\ValidationFailedException
     * @expectedExceptionMessage Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" failed validation.
     */
    public function testValidationFailedException()
    {
        $message = new DummyMessage('Hey');

        $violationList = $this->createMock(ConstraintViolationListInterface::class);
        $violationList
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($message)
            ->willReturn($violationList)
        ;
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->never())
            ->method('__invoke')
        ;

        (new ValidationMiddleware($validator))->handle($message, $next);
    }
}
