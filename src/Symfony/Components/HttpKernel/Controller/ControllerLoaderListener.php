<?php

namespace Symfony\Components\HttpKernel\Controller;

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
 * ControllerLoaderListener listen to the core.load_controller and finds the controller
 * to execute based on the request parameters.
 *
 * @package    Symfony
 * @subpackage Bundle_FrameworkBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerLoaderListener
{
    protected $manager;

    public function __construct(ControllerManagerInterface $manager)
    {
        $this->manager = $manager;
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

        if (false === $controller = $this->manager->getController($request)) {
            return false;
        }

        $arguments = $this->manager->getMethodArguments($request, $controller);

        $event->setReturnValue(array($controller, $arguments));

        return true;
    }
}
