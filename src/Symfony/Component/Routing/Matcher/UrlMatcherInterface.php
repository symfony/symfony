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

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * UrlMatcherInterface is the interface that all URL matcher classes must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface UrlMatcherInterface
{
    /**
     * Tries to match a URL path with a set of routes.
     *
     * As matching a path only is quite limiting, the UrlMatcher likely also needs to implement {@link \Symfony\Component\Routing\RequestContextAwareInterface}
     * to access more metadata about the request context like the domain and HTTP method. Alternatively, you can implement
     * {@link \Symfony\Component\Routing\Matcher\RequestMatcherInterface} to express that you also accept a {@link \Symfony\Component\HttpFoundation\Request}
     * object in the `match` method, that directly exposes all needed information.
     *
     * If the matcher can not find a corresponding resource, it must throw one of the exceptions documented below.
     *
     * @param string $pathinfo A string with the path component of a URL (raw format, i.e. not urldecoded).
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     *
     * @api
     */
    public function match($pathinfo);
}
