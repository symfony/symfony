<?php

namespace Symfony\Framework\FoundationBundle\Listener;

use Symfony\Framework\FoundationBundle\Controller\ControllerManager;
use Symfony\Components\HttpKernel\LoggerInterface;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ControllerLoader listen to the core.load_controller and finds the controller
 * to execute based on the request parameters.
 *
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerLoader
{
    protected $manager;
    protected $logger;

    public function __construct(ControllerManager $manager, LoggerInterface $logger = null)
    {
        $this->manager = $manager;
        $this->logger = $logger;
    }

    /**
     * Registers a core.load_controller listener.
     *
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.load_controller', array($this, 'resolve'));
    }

    /**
     * Creates the Controller associated with the given Request.
     *
     * @param Event $event An Event instance
     *
     * @return Boolean true if the controller has been found, false otherwise
     */
    public function resolve(Event $event)
    {
        $request = $event->getParameter('request');

        if (!$controller = $request->path->get('_controller')) {
            if (null !== $this->logger) {
                $this->logger->err('Unable to look for the controller as the "_controller" parameter is missing');
            }

            return false;
        }

        list($controller, $method) = $this->manager->findController($controller);
        $controller->setRequest($request);

        $arguments = $this->manager->getMethodArguments($request->path->all(), $controller, $method);

        $event->setReturnValue(array(array($controller, $method), $arguments));

        return true;
    }
}
