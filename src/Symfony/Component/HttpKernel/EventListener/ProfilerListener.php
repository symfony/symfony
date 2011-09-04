<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * ProfilerListener collects data for the current request by listening to the onKernelResponse event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerListener
{
    protected $profiler;
    protected $matcher;
    protected $onlyException;
    protected $onlyMasterRequests;
    protected $exception;
    protected $children;

    /**
     * Constructor.
     *
     * @param Profiler                $profiler           A Profiler instance
     * @param RequestMatcherInterface $matcher            A RequestMatcher instance
     * @param Boolean                 $onlyException      true if the profiler only collects data when an exception occurs, false otherwise
     * @param Boolean                 $onlyMasterRequests true if the profiler only collects data when the request is a master request, false otherwise
     */
    public function __construct(Profiler $profiler, RequestMatcherInterface $matcher = null, $onlyException = false, $onlyMasterRequests = false)
    {
        $this->profiler = $profiler;
        $this->matcher = $matcher;
        $this->onlyException = (Boolean) $onlyException;
        $this->onlyMasterRequests = (Boolean) $onlyMasterRequests;
        $this->children = array();
    }

    /**
     * Handles the onKernelException event.
     *
     * @param GetResponseForExceptionEvent $event A GetResponseForExceptionEvent instance
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($this->onlyMasterRequests && HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $this->exception = $event->getException();
    }

    /**
     * Handles the onKernelResponse event.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $master = HttpKernelInterface::MASTER_REQUEST === $event->getRequestType();
        if ($this->onlyMasterRequests && !$master) {
            return;
        }

        if ($this->onlyException && null === $this->exception) {
            return;
        }

        $exception = $this->exception;
        $this->exception = null;

        if (null !== $this->matcher && !$this->matcher->matches($event->getRequest())) {
            return;
        }

        if ($profile = $this->profiler->collect($event->getRequest(), $event->getResponse(), $exception)) {
            if ($master) {
                $this->profiler->saveProfile($profile);
                foreach ($this->children as $child) {
                    $child->setParent($profile);
                    $this->profiler->saveProfile($child);
                }
                $this->children = array();
            } else {
                $this->children[] = $profile;
            }
        }
    }
}
