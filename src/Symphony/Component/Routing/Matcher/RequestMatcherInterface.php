<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Matcher;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Routing\Exception\NoConfigurationException;
use Symphony\Component\Routing\Exception\ResourceNotFoundException;
use Symphony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * RequestMatcherInterface is the interface that all request matcher classes must implement.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface RequestMatcherInterface
{
    /**
     * Tries to match a request with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @return array An array of parameters
     *
     * @throws NoConfigurationException  If no routing configuration could be found
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     */
    public function matchRequest(Request $request);
}
