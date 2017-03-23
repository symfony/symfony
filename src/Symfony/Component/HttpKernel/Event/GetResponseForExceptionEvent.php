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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Allows to create a response for a thrown exception.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 *
 * You can also call setException() to replace the thrown exception. This
 * exception will be thrown if no response is set during processing of this
 * event.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GetResponseForExceptionEvent extends GetResponseEvent
{
    /**
     * The exception or error object.
     *
     * @var \Exception|\Throwable
     */
    private $exception;

    /**
     * @var bool
     */
    private $allowCustomResponseCode = false;

    /**
     * GetResponseForExceptionEvent constructor.
     * @param HttpKernelInterface   $kernel
     * @param Request               $request
     * @param int                   $requestType
     * @param \Exception|\Throwable $e
     */
    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, $e)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setException($e);
    }

    /**
     * Returns the thrown exception.
     *
     * @return \Exception|\Throwable The thrown exception or error
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     *
     * @param \Exception|\Throwable $exception The thrown exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
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
