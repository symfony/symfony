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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
final class InternalExceptionEvent extends ExceptionEvent
{
    private $event;
    private $exception;

    public function __construct(ErrorEvent $event)
    {
        $this->event = $event;

        KernelEvent::__construct($event->getKernel(), $event->getRequest(), $event->isMasterRequest() ? HttpKernelInterface::MASTER_REQUEST : HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * {@inheritdoc}
     */
    public function getException(): \Exception
    {
        return $this->exception ?? $this->exception = ($e = $this->event->getException()) instanceof \Exception ? $e : new FatalThrowableError($e);
    }

    /**
     * {@inheritdoc}
     */
    public function setException(\Exception $exception): void
    {
        if ($this->exception === $exception) {
            return;
        }

        $this->exception = null;
        $this->event->setException($exception);
    }

    /**
     * {@inheritdoc}
     */
    public function allowCustomResponseCode(): void
    {
        $this->event->allowCustomResponseCode();
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowingCustomResponseCode(): bool
    {
        return $this->event->isAllowingCustomResponseCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(): ?Response
    {
        return $this->event->getResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function setResponse(Response $response): void
    {
        $this->event->setResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function hasResponse(): bool
    {
        return $this->event->hasResponse();
    }
}
