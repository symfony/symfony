<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\LazyResponseException;

/**
 * Wraps a lazily computed response in a signaling exception.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LazyResponseEvent extends RequestEvent
{
    private parent $event;

    public function __construct(parent $event)
    {
        $this->event = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function setResponse(Response $response)
    {
        $this->stopPropagation();
        $this->event->stopPropagation();

        throw new LazyResponseException($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getKernel(): HttpKernelInterface
    {
        return $this->event->getKernel();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest(): Request
    {
        return $this->event->getRequest();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestType(): int
    {
        return $this->event->getRequestType();
    }

    /**
     * {@inheritdoc}
     */
    public function isMainRequest(): bool
    {
        return $this->event->isMainRequest();
    }
}
