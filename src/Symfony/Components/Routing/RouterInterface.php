<?php

namespace Symfony\Components\Routing;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RouterInterface is the interface that all Router classes must implements.
 *
 * This interface is the concatenation of UrlMatcherInterface and UrlGeneratorInterface.
 *
 * @package    Symfony
 * @subpackage Components_Routing
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface RouterInterface
{
    /**
     * Tries to match a URL with a set of routes.
     *
     * Returns false if no route matches the URL.
     *
     * @param  string $url URL to be parsed
     *
     * @return array|false An array of parameters or false if no route matches
     */
    public function match($url);

    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     */
    public function generate($name, array $parameters, $absolute = false);
}
