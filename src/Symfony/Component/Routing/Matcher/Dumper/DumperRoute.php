<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\Routing\Route;

/**
 * Container for a Route.
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 *
 * @internal
 */
class DumperRoute
{
    private $name;
    private $route;

    public function __construct(string $name, Route $route)
    {
        $this->name = $name;
        $this->route = $route;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }
}
