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

use Symphony\Component\Routing\Exception\NoConfigurationException;
use Symphony\Component\Routing\RequestContextAwareInterface;
use Symphony\Component\Routing\Exception\ResourceNotFoundException;
use Symphony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * UrlMatcherInterface is the interface that all URL matcher classes must implement.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface UrlMatcherInterface extends RequestContextAwareInterface
{
    /**
     * Tries to match a URL path with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param string $pathinfo The path info to be parsed (raw format, i.e. not urldecoded)
     *
     * @return array An array of parameters
     *
     * @throws NoConfigurationException  If no routing configuration could be found
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    public function match($pathinfo);
}
