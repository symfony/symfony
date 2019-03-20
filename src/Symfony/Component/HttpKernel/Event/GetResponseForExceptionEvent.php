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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @deprecated since Symfony 4.3, use ExceptionEvent instead
 */
class GetResponseForExceptionEvent extends RequestEvent
{
    /**
     * The exception object.
     *
     * @var \Exception
     */
    private $exception;

    /**
     * @var bool
     */
    private $allowCustomResponseCode = false;

    public function __construct(HttpKernelInterface $kernel, Request $request, int $requestType, \Exception $e)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setException($e);
    }

    /**
     * Returns the thrown exception.
     *
     * @return \Exception The thrown exception
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
     * @param \Exception $exception The thrown exception
     */
    public function setException(\Exception $exception)
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
