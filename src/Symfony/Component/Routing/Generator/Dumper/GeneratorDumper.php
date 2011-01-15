<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator\Dumper;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * GeneratorDumper is the base class for all built-in generator dumpers.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class GeneratorDumper implements GeneratorDumperInterface
{
    protected $routes;

    /**
     * Constructor.
     *
     * @param RouteCollection $routes The RouteCollection to dump
     */
    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }
}
