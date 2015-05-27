<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Profiler\ProfileData\RequestData;
use Symfony\Component\Profiler\ProfileData\RouterData;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterDataCollector extends AbstractDataCollector implements EventSubscriberInterface, RuntimeDataCollectorInterface
{
    protected $requestStack;
    protected $controllers;
    protected $responses;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        $this->controllers = new \SplObjectStorage();
        $this->responses = new \SplObjectStorage();

        $this->data = array(
            'redirect' => false,
            'url' => null,
            'route' => null,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!isset($this->responses[$request])) {
            return;
        }
        $response = $this->responses[$request];

        $route = null;
        if ($response instanceof RedirectResponse) {
            if ($this->controllers->contains($request)) {
                $route = $this->guessRoute($request, $this->controllers[$request]);
            }
        }

        unset($this->controllers[$request]);
        return new RouterData($response, $route);
    }

    protected function guessRoute(Request $request, $controller)
    {
        return 'n/a';
    }

    /**
     * Remembers the controller associated to each request.
     *
     * @param FilterControllerEvent $event The filter controller event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $this->responses[$event->getRequest()] = $event->getResponse();
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => array('onKernelException', -50)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}
