<?php

namespace Symfony\Component\Routing\Matcher;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * UrlMatcherInterface is the interface that all URL matcher classes must implements.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface UrlMatcherInterface
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
    function match($url);
}
