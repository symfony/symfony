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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
final class ControllerArgumentsEvent extends KernelEvent
{
    private ControllerEvent $controllerEvent;
    private array $arguments;

    public function __construct(HttpKernelInterface $kernel, callable|ControllerEvent $controller, array $arguments, Request $request, ?int $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        if (!$controller instanceof ControllerEvent) {
            $controller = new ControllerEvent($kernel, $controller, $request, $requestType);
        }

        $this->controllerEvent = $controller;
        $this->arguments = $arguments;
    }

    public function getController(): callable
    {
        return $this->controllerEvent->getController();
    }

    /**
     * @param array<class-string, list<object>>|null $attributes
     */
    public function setController(callable $controller, array $attributes = null): void
    {
        $this->controllerEvent->setController($controller, $attributes);
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @return array<class-string, list<object>>
     */
    public function getAttributes(): array
    {
        return $this->controllerEvent->getAttributes();
    }
}
