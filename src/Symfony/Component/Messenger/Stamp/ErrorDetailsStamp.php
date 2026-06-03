<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;

/**
 * Stamp applied when a messages fails due to an exception in the handler.
 */
final class ErrorDetailsStamp implements StampInterface
{
    public function __construct(
        private string $exceptionClass,
        private int|string $exceptionCode,
        private string $exceptionMessage,
        private ?FlattenException $flattenException = null,
    ) {
    }

    public static function create(\Throwable $throwable): self
    {
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getPrevious();
        }

        $flattenException = null;
        if (!$throwable instanceof RecoverableExceptionInterface && class_exists(FlattenException::class)) {
            $flattenException = FlattenException::createFromThrowable($throwable);
            $flattenException->setTrace([], $throwable->getFile(), $throwable->getLine());
        }

        return new self($throwable::class, $throwable->getCode(), $throwable->getMessage(), $flattenException);
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function getExceptionCode(): int|string
    {
        return $this->exceptionCode;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function getFlattenException(): ?FlattenException
    {
        return $this->flattenException;
    }

    public function equals(?self $that): bool
    {
        if (null === $that) {
            return false;
        }

        if ($this->flattenException && $that->flattenException) {
            return $this->flattenException->getClass() === $that->flattenException->getClass()
                && $this->flattenException->getCode() === $that->flattenException->getCode()
                && $this->flattenException->getFile() === $that->flattenException->getFile()
                && $this->flattenException->getLine() === $that->flattenException->getLine();
        }

        return $this->exceptionClass === $that->exceptionClass
            && $this->exceptionCode === $that->exceptionCode
            && $this->exceptionMessage === $that->exceptionMessage;
    }
}
