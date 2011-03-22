<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

/**
 * UrlMatcherInterface is the interface that all URL matcher classes must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UrlMatcherInterface
{
    /**
     * Tries to match a URL with a set of routes.
     *
     * @param  string $pathinfo The path info to be parsed
     *
     * @return array An array of parameters
     *
     * @throws NotFoundException         If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    function match($pathinfo);
}
