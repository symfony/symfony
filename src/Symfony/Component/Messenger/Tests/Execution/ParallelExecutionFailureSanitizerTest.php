<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\Execution\ParallelExecutionFailureSanitizer;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ParallelExecutionFailureSanitizerTest extends TestCase
{
    private string|false $exceptionIgnoreArgs;

    protected function setUp(): void
    {
        // DispatchTask::run() sets it in workers; without it, the arguments in the trace cannot be serialized
        $this->exceptionIgnoreArgs = ini_set('zend.exception_ignore_args', '1');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->exceptionIgnoreArgs);
    }

    public function testSerializableErrorsAreKept()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $exception = new \RuntimeException('boom', 42);

        $error = ParallelExecutionFailureSanitizer::sanitizeError(new HandlerFailedException($envelope, ['handler' => $exception]), $envelope);

        $this->assertSame($exception, $error->getWrappedExceptions()['handler']);
    }

    public function testUnserializableErrorsAreReplaced()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $exception = new NotSerializableException('boom', 42);

        $error = ParallelExecutionFailureSanitizer::sanitizeError(new HandlerFailedException($envelope, ['handler' => $exception]), $envelope);
        $error = unserialize(serialize($error));

        $this->assertInstanceOf(HandlerFailedException::class, $error);
        $wrapped = $error->getWrappedExceptions()['handler'];
        $this->assertSame(RuntimeException::class, $wrapped::class);
        $this->assertSame('boom', $wrapped->getMessage());
        $this->assertSame(42, $wrapped->getCode());
    }

    public function testPreviousOfUnserializableErrorsIsKept()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $previous = new \LogicException('cause');
        $exception = new NotSerializableException('boom', 0, new NotSerializableException('inner', 0, $previous));

        $error = unserialize(serialize(ParallelExecutionFailureSanitizer::sanitizeError($exception, $envelope)));

        $this->assertSame('boom', $error->getMessage());
        $this->assertSame(RuntimeException::class, $error->getPrevious()::class);
        $this->assertSame('inner', $error->getPrevious()->getMessage());
        $this->assertInstanceOf(\LogicException::class, $error->getPrevious()->getPrevious());
        $this->assertSame('cause', $error->getPrevious()->getPrevious()->getMessage());
    }

    public function testUnserializableUnrecoverableErrorsStayUnrecoverable()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $exception = new class('boom') extends NotSerializableException implements UnrecoverableExceptionInterface {};

        $error = ParallelExecutionFailureSanitizer::sanitizeError($exception, $envelope);

        $this->assertInstanceOf(UnrecoverableMessageHandlingException::class, unserialize(serialize($error)));
    }

    public function testUnserializableRecoverableErrorsStayRecoverable()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $exception = new class('boom') extends NotSerializableException implements RecoverableExceptionInterface {
            public function getRetryDelay(): ?int
            {
                return 1000;
            }

            public function forceRetry(): bool
            {
                return false;
            }
        };

        $error = unserialize(serialize(ParallelExecutionFailureSanitizer::sanitizeError($exception, $envelope)));

        $this->assertInstanceOf(RecoverableMessageHandlingException::class, $error);
        $this->assertSame(1000, $error->getRetryDelay());
        $this->assertFalse($error->forceRetry());
    }

    public function testUnserializableErrorsThatAreBothRecoverableAndUnrecoverableStayRecoverable()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $exception = new class('boom') extends NotSerializableException implements RecoverableExceptionInterface, UnrecoverableExceptionInterface {
            public function getRetryDelay(): ?int
            {
                return null;
            }
        };

        $error = unserialize(serialize(ParallelExecutionFailureSanitizer::sanitizeError($exception, $envelope)));

        $this->assertInstanceOf(RecoverableMessageHandlingException::class, $error);
        $this->assertTrue($error->forceRetry());
    }

    public function testUnserializableValidationErrorsAreReplaced()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $violations = new ConstraintViolationList([new ConstraintViolation('Invalid.', null, [], null, null, null, null, null, new Callback(static fn () => null))]);

        $error = ParallelExecutionFailureSanitizer::sanitizeError(new ValidationFailedException($envelope->getMessage(), $violations, $envelope), $envelope);
        $error = unserialize(serialize($error));

        $this->assertSame(RuntimeException::class, $error::class);
        $this->assertSame(\sprintf('Message of type "%s" failed validation.', DummyMessage::class), $error->getMessage());
    }
}

class NotSerializableException extends \RuntimeException
{
    private \Closure $callback;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->callback = static fn () => null;
    }
}
