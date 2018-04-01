<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Event;

use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * Allows filtering of controller arguments.
 *
 * You can call getController() to retrieve the controller and getArguments
 * to retrieve the current arguments. With setArguments() you can replace
 * arguments that are used to call the controller.
 *
 * Arguments set in the event must be compatible with the signature of the
 * controller.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class FilterControllerArgumentsEvent extends FilterControllerEvent
{
    private $arguments;

    public function __construct(HttpKernelInterface $kernel, callable $controller, array $arguments, Request $request, ?int $requestType)
    {
        parent::__construct($kernel, $controller, $request, $requestType);

        $this->arguments = $arguments;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }
}
