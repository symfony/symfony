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
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
abstract class RedirectableRequestMatcher extends RequestMatcher
{
    /**
     * {@inheritdoc}
     */
    public function matchRequest(Request $request)
    {
        try {
            $parameters = parent::matchRequest($request);
        } catch (ResourceNotFoundException $e) {
            $newScheme = 'http' === $request->getScheme() ? 'https' : 'http';
            $newRequest = clone $request;
            // TODO: This is not available. Another idea is to have a specialized ResourceNotFoundException
            //$newRequest->setScheme($newScheme);

            try {
                parent::matchRequest($newRequest);

                return $this->redirect($request->getPathInfo(), null, $newScheme);
            } catch (ResourceNotFoundException $e2) {
                throw $e;
            }

            if ('/' === substr($request->getPathInfo(), -1) || !in_array($request->getMethod(), array('HEAD', 'GET'))) {
                throw $e;
            }

            $newRequest = clone $request;
            $newRequest->setPathInfo($request->getPathInfo().'/');

            try {
                parent::matchRequest($newRequest);

                return $this->redirect($newRequest->getPathInfo(), null);
            } catch (ResourceNotFoundException $e2) {
                // TODO: MethodNotAllowedException is not handled
                throw $e;
            }
        }

        return $parameters;
    }

    /**
     * Returns parameters for handling a redirect.
     *
     * @param string      $path   The path info to redirect to.
     * @param string      $route  The route name that matched
     * @param string|null $scheme The URL scheme (null to keep the current one)
     *
     * @return array An array of parameters
     */
    abstract protected function redirect($path, $route, $scheme = null);
}
