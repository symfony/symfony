<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\DataCollector;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\RedirectResponse;
use Symphony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RouterDataCollector extends DataCollector
{
    /**
     * @var \SplObjectStorage
     */
    protected $controllers;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if ($response instanceof RedirectResponse) {
            $this->data['redirect'] = true;
            $this->data['url'] = $response->getTargetUrl();

            if ($this->controllers->contains($request)) {
                $this->data['route'] = $this->guessRoute($request, $this->controllers[$request]);
            }
        }

        unset($this->controllers[$request]);
    }

    public function reset()
    {
        $this->controllers = new \SplObjectStorage();

        $this->data = array(
            'redirect' => false,
            'url' => null,
            'route' => null,
        );
    }

    protected function guessRoute(Request $request, $controller)
    {
        return 'n/a';
    }

    /**
     * Remembers the controller associated to each request.
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * @return bool Whether this request will result in a redirect
     */
    public function getRedirect()
    {
        return $this->data['redirect'];
    }

    /**
     * @return string|null The target URL
     */
    public function getTargetUrl()
    {
        return $this->data['url'];
    }

    /**
     * @return string|null The target route
     */
    public function getTargetRoute()
    {
        return $this->data['route'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}
