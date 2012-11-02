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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * RequestMatcherInterface is the interface that all request matcher classes must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RequestMatcherInterface extends UrlMatcherInterface
{
    /**
     * Tries to match a Request or a URL path with a set of routes.
     *
     * In contrast to the UrlMatcherInterface, a request matcher also accepts a Request object.
     *
     * @see UrlMatcherInterface
     *
     * @param Request|string $request A Request or a string with the path component of a URL (raw format, i.e. not urldecoded).
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     */
    public function match($request);
}
