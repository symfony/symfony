<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpKernel\Exception\ControllerLayoutException;

/**
 * Responsible for build FQCN::action from bundle + controller name + action name
 * and parse FQCN::action into bundle + controller name + action name.
 *
 * @author Pavel Batanov <pavel@batanov.me>
 */
interface ControllerLayoutInterface
{
    /**
     * Decompose controller string into bundle, controller and action.
     *
     * @param string $controller
     *
     * @return ActionReference
     *
     * @throws ControllerLayoutException
     */
    public function parse(string $controller): ActionReference;

    /**
     * Builds a controller string for given bundle, controller, and action.
     *
     * @param ActionReference $action
     *
     * @return string
     *
     * @throws ControllerLayoutException
     */
    public function build(ActionReference $action): string;
}
