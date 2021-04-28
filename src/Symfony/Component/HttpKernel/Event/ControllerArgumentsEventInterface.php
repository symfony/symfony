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

/**
 * Represents an event containing a controller and arguments.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface ControllerArgumentsEventInterface
{
    public function getController(): callable;

    public function setController(callable $controller);

    public function getArguments(): array;

    public function setArguments(array $arguments);
}
