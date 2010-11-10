<?php

namespace Symfony\Component\Routing;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RouteCompilerInterface is the interface that all RouteCompiler classes must implements.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface RouteCompilerInterface
{
    /**
     * Compiles the current route instance.
     *
     * @param Route $route A Route instance
     *
     * @return CompiledRoute A CompiledRoute instance
     */
    function compile(Route $route);
}
