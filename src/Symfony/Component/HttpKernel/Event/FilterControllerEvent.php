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
 * Allows to filter a controller callable
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in for processing
 * a request.
 *
 * Controllers should be callables.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FilterControllerEvent extends KernelEvent
{
    /**
     * The current controller
     * @var callable
     */
    private $controller;

    public function __construct(HttpKernelInterface $kernel, $controller, Request $request, $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setController($controller);
    }

    /**
     * Returns the current controller
     *
     * @return callable
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Sets a new controller
     *
     * @param callable $controller
     */
    public function setController($controller)
    {
        // controller must be a callable
        if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s given).', $this->varToString($controller)));
        }

        $this->controller = $controller;
    }

    private function varToString($var)
    {
        if (is_object($var)) {
            return sprintf('[object](%s)', get_class($var));
        }

        if (is_array($var)) {
            $a = array();
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => %s', $k, $this->varToString($v));
            }

            return sprintf("[array](%s)", implode(', ', $a));
        }

        if (is_resource($var)) {
            return '[resource]';
        }

        return str_replace("\n", '', var_export((string) $var, true));
    }
}