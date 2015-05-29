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

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Profiler\ProfileData\RequestData;

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestDataCollector extends AbstractDataCollector implements EventSubscriberInterface, RuntimeDataCollectorInterface
{
    private $requestStack;
    private $responses;
    private $controllers;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        $this->controllers = new \SplObjectStorage();
        $this->responses = new \SplObjectStorage();
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

        $controller = null;
        if (isset($this->controllers[$request])) {
            $controller = $this->controllers[$request];
            unset($this->controllers[$request]);
        }

        return new RequestData($request, $this->responses[$request], $controller);
    }

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
        return 'request';
    }
}
