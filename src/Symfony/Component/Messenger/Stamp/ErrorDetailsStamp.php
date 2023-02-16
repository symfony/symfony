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

/**
 * Stamp applied when a messages fails due to an exception in the handler.
 */
final class ErrorDetailsStamp implements StampInterface
{
    /** @var string */
    private $exceptionClass;

    /** @var int|string */
    private $exceptionCode;

    /** @var string */
    private $exceptionMessage;

    /** @var FlattenException|null */
    private $flattenException;

    /**
     * @param int|string $exceptionCode
     */
    public function __construct(string $exceptionClass, $exceptionCode, string $exceptionMessage, FlattenException $flattenException = null)
    {
        $this->exceptionClass = $exceptionClass;
        $this->exceptionCode = $exceptionCode;
        $this->exceptionMessage = $exceptionMessage;
        $this->flattenException = $flattenException;
    }

    public static function create(\Throwable $throwable): self
    {
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getPrevious();
        }

        $flattenException = null;
        if (class_exists(FlattenException::class)) {
            $flattenException = FlattenException::createFromThrowable($throwable);
        }

        return new self(\get_class($throwable), $throwable->getCode(), $throwable->getMessage(), $flattenException);
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function getExceptionCode()
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
                && $this->flattenException->getMessage() === $that->flattenException->getMessage();
        }

        return $this->exceptionClass === $that->exceptionClass
            && $this->exceptionCode === $that->exceptionCode
            && $this->exceptionMessage === $that->exceptionMessage;
    }
}
