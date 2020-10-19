<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @deprecated since Symfony 4.3, use ExceptionEvent instead
 */
class GetResponseForExceptionEvent extends RequestEvent
{
    private $throwable;
    private $exception;
    private $allowCustomResponseCode = false;

    public function __construct(HttpKernelInterface $kernel, Request $request, int $requestType, \Throwable $e)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setThrowable($e);
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     */
    public function setThrowable(\Throwable $exception): void
    {
        $this->exception = null;
        $this->throwable = $exception;
    }

    /**
     * @deprecated since Symfony 4.4, use getThrowable instead
     *
     * @return \Exception The thrown exception
     */
    public function getException()
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use "getThrowable()" instead.', __METHOD__), \E_USER_DEPRECATED);

        return $this->exception ?? $this->exception = $this->throwable instanceof \Exception ? $this->throwable : new FatalThrowableError($this->throwable);
    }

    /**
     * @deprecated since Symfony 4.4, use setThrowable instead
     *
     * @param \Exception $exception The thrown exception
     */
    public function setException(\Exception $exception)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use "setThrowable()" instead.', __METHOD__), \E_USER_DEPRECATED);

        $this->throwable = $this->exception = $exception;
    }

    /**
     * Mark the event as allowing a custom response code.
     */
    public function allowCustomResponseCode()
    {
        $this->allowCustomResponseCode = true;
    }

    /**
     * Returns true if the event allows a custom response code.
     *
     * @return bool
     */
    public function isAllowingCustomResponseCode()
    {
        return $this->allowCustomResponseCode;
    }
}
