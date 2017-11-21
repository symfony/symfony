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

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestCollector;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * RequestDataCollector.
 *
 * @author Jules Pietri <jusles@heahprod.com>
 */
class RequestDataCollector extends BaseRequestCollector implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        if ($parentRequestAttributes = $request->attributes->get('_forwarded')) {
            if ($parentRequestAttributes instanceof ParameterBag) {
                $parentRequestAttributes->set('_forward_token', $response->headers->get('x-debug-token'));
            }
        }
        if ($request->attributes->has('_forward_controller')) {
            $this->data['forward'] = array(
                'token' => $request->attributes->get('_forward_token'),
                'controller' => $this->parseController($request->attributes->get('_forward_controller')),
            );
        }
    }

    /**
     * Gets the parsed forward controller.
     *
     * @return array|bool An array with keys 'token' the forward profile token, and
     *                    'controller' the parsed forward controller, false otherwise
     */
    public function getForward()
    {
        return isset($this->data['forward']) ? $this->data['forward'] : false;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();

        if ($parentRequestAttributes = $event->getRequest()->attributes->get('_forwarded')) {
            if ($parentRequestAttributes instanceof ParameterBag) {
                $parentRequestAttributes->set('_forward_controller', $event->getController());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }
}
