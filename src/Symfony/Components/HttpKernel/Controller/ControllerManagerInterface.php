<?php

namespace Symfony\Components\HttpKernel\Controller;

use Symfony\Components\HttpFoundation\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A ControllerManagerInterface implementation knows how to determine the
 * controller to execute based on a Request object.
 *
 * It can also determine the arguments to pass to the Controller.
 *
 * A Controller can be any valid PHP callable.
 *
 * @package    Symfony
 * @subpackage Bundle_FrameworkBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ControllerManagerInterface
{
    /**
     * Returns the Controller instance associated with a Request.
     *
     * As several managers can exist for a single application, a manager must
     * return false when it is not able to determine the controller.
     *
     * The manager must only throw an exception when it should be able to load
     * controller but cannot because of some errors made by the developer.
     *
     * @param \Symfony\Components\HttpFoundation\Request $request A Request instance
     *
     * @return mixed|Boolean A PHP callable representing the Controller,
     *                       or false if this manager is not able to determine the controller
     *
     * @throws \InvalidArgumentException|\LogicException If the controller can't be found
     */
    public function getController(Request $request);

    /**
     * Returns the arguments to pass to the controller.
     *
     * @param \Symfony\Components\HttpFoundation\Request $request    A Request instance
     * @param mixed                                      $controller A PHP callable
     *
     * @throws \RuntimeException When value for argument given is not provided
     */
    public function getMethodArguments(Request $request, $controller);
}
