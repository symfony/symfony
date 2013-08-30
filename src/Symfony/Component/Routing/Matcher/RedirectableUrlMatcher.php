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
use Symfony\Component\Routing\Route;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
abstract class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        try {
            $parameters = parent::match($pathinfo);
        } catch (ResourceNotFoundException $e) {
            if (!in_array($this->context->getMethod(), array('HEAD', 'GET'))) {
                throw $e;
            }

            if ('/' === substr($pathinfo, -1)) {
                $routeForRedirect = substr($pathinfo, 0, -1);
            } else {
                $routeForRedirect = $pathinfo . '/';
            }

            try {
                parent::match($routeForRedirect);

                return $this->redirect($routeForRedirect, null);
            } catch (ResourceNotFoundException $e2) {
                throw $e;
            }
        }

        return $parameters;
    }

    /**
     * {@inheritDoc}
     */
    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        // check HTTP scheme requirement
        $scheme = $route->getRequirement('_scheme');
        if ($scheme && $this->context->getScheme() !== $scheme) {
            return array(self::ROUTE_MATCH, $this->redirect($pathinfo, $name, $scheme));
        }

        return array(self::REQUIREMENT_MATCH, null);
    }
}
