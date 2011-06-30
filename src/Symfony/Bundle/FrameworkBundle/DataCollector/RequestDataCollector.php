<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestDataCollector extends BaseRequestDataCollector
{
    protected $controllers;

    public function __construct()
    {
        $this->controllers = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        $this->data['route'] = $request->attributes->get('_route');
        $this->data['controller'] = 'n/a';

        if (isset($this->controllers[$request])) {
            $controller = $this->controllers[$request];
            if (is_array($controller)) {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
                $this->data['controller'] = array(
                    'class'  => get_class($controller[0]),
                    'method' => $controller[1],
                    'file'   => $r->getFilename(),
                    'line'   => $r->getStartLine(),
                );
            } elseif ($controller instanceof \Closure) {
                $this->data['controller'] = 'Closure';
            } else {
                $this->data['controller'] = (string) $controller ?: 'n/a';
            }
            unset($this->controllers[$request]);
        }
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * Gets the route.
     *
     * @return string The route
     */
    public function getRoute()
    {
        return $this->data['route'];
    }

    /**
     * Gets the controller.
     *
     * @return string The controller as a string
     */
    public function getController()
    {
        return $this->data['controller'];
    }
}
