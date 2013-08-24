<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.1.0
 */
class RouterDataCollector extends DataCollector
{
    protected $controllers;

    /**
     * @since v2.1.0
     */
    public function __construct()
    {
        $this->controllers = new \SplObjectStorage();

        $this->data = array(
            'redirect' => false,
            'url'      => null,
            'route'    => null,
        );
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
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
    }

    /**
     * @since v2.1.0
     */
    protected function guessRoute(Request $request, $controller)
    {
        return 'n/a';
    }

    /**
     * Remembers the controller associated to each request.
     *
     * @param FilterControllerEvent $event The filter controller event
     *
     * @since v2.1.0
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * @return Boolean Whether this request will result in a redirect
     *
     * @since v2.1.0
     */
    public function getRedirect()
    {
        return $this->data['redirect'];
    }

    /**
     * @return string|null The target URL
     *
     * @since v2.1.0
     */
    public function getTargetUrl()
    {
        return $this->data['url'];
    }

    /**
     * @return string|null The target route
     *
     * @since v2.1.0
     */
    public function getTargetRoute()
    {
        return $this->data['route'];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getName()
    {
        return 'router';
    }
}
