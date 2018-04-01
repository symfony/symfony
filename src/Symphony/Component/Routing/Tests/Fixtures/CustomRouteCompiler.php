<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Fixtures;

use Symphony\Component\Routing\Route;
use Symphony\Component\Routing\RouteCompiler;

class CustomRouteCompiler extends RouteCompiler
{
    /**
     * {@inheritdoc}
     */
    public static function compile(Route $route)
    {
        return new CustomCompiledRoute('', '', array(), array());
    }
}
