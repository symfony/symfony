<?php

namespace Symfony\Component\Routing\Generator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * UrlGeneratorInterface is the interface that all URL generator classes must implements.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface UrlGeneratorInterface
{
    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     */
    function generate($name, array $parameters, $absolute = false);
}
