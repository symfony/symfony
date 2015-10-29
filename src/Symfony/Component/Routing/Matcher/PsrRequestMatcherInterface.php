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

use Psr\Http\Message\RequestInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * Routing matcher interface to find route parameters based on a PSR-7 RequestInterface.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
interface PsrRequestMatcherInterface
{
    /**
     * Returns the routing parameters of the route that matches the given request.
     *
     * If no route matches, one of the exceptions documented below must be thrown.
     *
     * @param RequestInterface $request The request to match against
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     */
    public function matchPsrRequest(RequestInterface $request);
}
