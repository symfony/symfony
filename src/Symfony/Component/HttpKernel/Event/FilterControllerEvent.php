<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Allows filtering of a controller callable.
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in the processing
 * of the request.
 *
 * Controllers should be callables.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class FilterControllerEvent extends KernelEvent
{
    /**
     * The current controller.
     *
     * @var callable
     */
    private $controller;

    public function __construct(HttpKernelInterface $kernel, $controller, Request $request, $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setController($controller);
    }

    /**
     * Returns the current controller.
     *
     * @return callable
     *
     * @api
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Sets a new controller.
     *
     * @param callable $controller
     *
     * @throws \LogicException
     *
     * @api
     */
    public function setController(callable $controller)
    {
        $this->controller = $controller;
    }
}
