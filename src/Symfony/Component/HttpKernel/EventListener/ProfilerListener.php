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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ProfilerListener collects data for the current request by listening to the onKernelResponse event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerListener implements EventSubscriberInterface
{
    protected $profiler;
    protected $matcher;
    protected $onlyException;
    protected $onlyMasterRequests;
    protected $exception;
    protected $children;
    protected $requests;
    protected $profiles;

    /**
     * Constructor.
     *
     * @param Profiler                $profiler           A Profiler instance
     * @param RequestMatcherInterface $matcher            A RequestMatcher instance
     * @param bool                    $onlyException      true if the profiler only collects data when an exception occurs, false otherwise
     * @param bool                    $onlyMasterRequests true if the profiler only collects data when the request is a master request, false otherwise
     */
    public function __construct(Profiler $profiler, RequestMatcherInterface $matcher = null, $onlyException = false, $onlyMasterRequests = false)
    {
        $this->profiler = $profiler;
        $this->matcher = $matcher;
        $this->onlyException = (bool) $onlyException;
        $this->onlyMasterRequests = (bool) $onlyMasterRequests;
        $this->children = new \SplObjectStorage();
        $this->profiles = array();
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

    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->requests[] = $event->getRequest();
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

        $request = $event->getRequest();
        $exception = $this->exception;
        $this->exception = null;

        if (null !== $this->matcher && !$this->matcher->matches($request)) {
            return;
        }

        if (!$profile = $this->profiler->collect($request, $event->getResponse(), $exception)) {
            return;
        }

        $this->profiles[] = $profile;

        if (null !== $exception) {
            foreach ($this->profiles as $profile) {
                $this->profiler->saveProfile($profile);
            }

            return;
        }

        // keep the profile as the child of its parent
        if (!$master) {
            array_pop($this->requests);

            $parent = end($this->requests);

            // when simulating requests, we might not have the parent
            if ($parent) {
                $profiles = isset($this->children[$parent]) ? $this->children[$parent] : array();
                $profiles[] = $profile;
                $this->children[$parent] = $profiles;
            }
        }

        if (isset($this->children[$request])) {
            foreach ($this->children[$request] as $child) {
                $profile->addChild($child);
            }
            $this->children[$request] = array();
        }

        if ($master) {
            $this->saveProfiles($profile);

            $this->children = new \SplObjectStorage();
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // kernel.request must be registered as early as possible to not break
            // when an exception is thrown in any other kernel.request listener
            KernelEvents::REQUEST => array('onKernelRequest', 1024),
            KernelEvents::RESPONSE => array('onKernelResponse', -100),
            KernelEvents::EXCEPTION => 'onKernelException',
        );
    }

    /**
     * Saves the profile hierarchy.
     *
     * @param Profile $profile The root profile
     */
    private function saveProfiles(Profile $profile)
    {
        $this->profiler->saveProfile($profile);
        foreach ($profile->getChildren() as $profile) {
            $this->saveProfiles($profile);
        }
    }
}
