<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ValidationFailedExceptionTest extends TestCase
{
    public function testItIsRenderedAsUnprocessableEntity()
    {
        $exception = $this->createException();
        $event = $this->createExceptionEvent($exception);

        (new ErrorListener('not used'))->logKernelException($event);

        $throwable = $event->getThrowable();

        $this->assertInstanceOf(HttpExceptionInterface::class, $throwable);
        $this->assertSame(422, $throwable->getStatusCode());
    }

    public function testTheViolationsStayReachableFromTheHttpException()
    {
        $exception = $this->createException();
        $event = $this->createExceptionEvent($exception);

        (new ErrorListener('not used'))->logKernelException($event);

        $this->assertSame($exception, $event->getThrowable()->getPrevious());
    }

    public function testAnExplicitStatusCodeWins()
    {
        $exception = $this->createException();
        $event = $this->createExceptionEvent($exception);

        (new ErrorListener('not used', null, false, [
            ValidationFailedException::class => ['log_level' => 'warning', 'status_code' => 400],
        ]))->logKernelException($event);

        $throwable = $event->getThrowable();

        $this->assertInstanceOf(HttpExceptionInterface::class, $throwable);
        $this->assertSame(400, $throwable->getStatusCode());
    }

    private function createException(): ValidationFailedException
    {
        return new ValidationFailedException('foo', new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], 'foo', 'name', ''),
        ]));
    }

    private function createExceptionEvent(ValidationFailedException $exception): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
