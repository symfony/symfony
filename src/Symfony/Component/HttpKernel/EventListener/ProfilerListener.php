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

use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Profiler\EventListener\HttpProfilerListener;

/**
 * ProfilerListener collects data for the current request by listening to the kernel events.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\Profiler\EventListener\HttpProfileListener instead.
 */
class ProfilerListener extends HttpProfilerListener
{
    protected $requests = array();

    /**
     * Constructor.
     *
     * @param Profiler $profiler A Profiler instance
     * @param RequestMatcherInterface|null $matcher A RequestMatcher instance
     * @param bool $onlyException true if the profiler only collects data when an exception occurs, false otherwise
     * @param bool $onlyMasterRequests true if the profiler only collects data when the request is a master request, false otherwise
     * @param RequestStack|null $requestStack A RequestStack instance
     */
    public function __construct(Profiler $profiler, RequestMatcherInterface $matcher = null, $onlyException = false, $onlyMasterRequests = false, RequestStack $requestStack = null)
    {
        if (null === $requestStack) {
            // Prevent the deprecation notice to be triggered all the time.
            // The onKernelRequest() method fires some logic only when the
            // RequestStack instance is not provided as a dependency.
            trigger_error('Since version 2.4, the ' . __METHOD__ . ' method must accept a RequestStack instance to get the request instead of using the ' . __CLASS__ . '::onKernelRequest method that will be removed in 3.0.', E_USER_DEPRECATED);
        }

        parent::__construct($profiler, $requestStack, $matcher, $onlyException, $onlyMasterRequests);
    }

    /**
     * @deprecated since version 2.4, to be removed in 3.0.
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (null === $this->requestStack) {
            $this->requests[] = $event->getRequest();
        }
    }

    /**
     * Handles the onKernelResponse event.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $master = $event->isMasterRequest();
        if ($this->onlyMasterRequests && !$master) {
            return;
        }

        if ($this->onlyException && null === $this->exception) {
            return;
        }

        $request = $event->getRequest();
        $exception = $this->exception;
        $this->exception = null;

        if (null !== $this->matcher && !$this->matcher->matches($request)) {
            return;
        }

        $this->profiler->addResponse($request, $event->getResponse());

        if (!$profile = $this->profiler->collect($request, $event->getResponse(), $exception)) {
            return;
        }

        $this->profiles[$request] = $profile;

        // "if" to be removed when requestStack is required
        if (null !== $this->requestStack) {
            $this->parents[$request] = $this->requestStack->getParentRequest();
        } elseif (!$master) {
            // to be removed when requestStack is required
            array_pop($this->requests);

            $this->parents[$request] = end($this->requests);
        }
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        parent::onKernelTerminate($event);
        $this->requests = array();
    }

    public static function getSubscribedEvents()
    {
        return array_merge(array(
            // kernel.request must be registered as early as possible to not break
            // when an exception is thrown in any other kernel.request listener
            KernelEvents::REQUEST => array('onKernelRequest', 1024)
        ), parent::getSubscribedEvents());
    }
}
