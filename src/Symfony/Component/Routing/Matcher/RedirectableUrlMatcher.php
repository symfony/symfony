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

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        try {
            $parameters = $this->matchCollectionByTrailingSlashSupport($pathinfo, false);
        } catch (ResourceNotFoundException $e) {
            if ('/' === substr($pathinfo, -1) || !\in_array($this->context->getMethod(), ['HEAD', 'GET'])) {
                throw $e;
            }

            try {
                $parameters = $this->matchCollectionByTrailingSlashSupport($pathinfo.'/', true);

                return array_replace($parameters, $this->redirect($pathinfo.'/', isset($parameters['_route']) ? $parameters['_route'] : null));
            } catch (ResourceNotFoundException $e2) {
                throw $e;
            }
        } catch (MethodNotAllowedException $e) {
            try {
                $parameters = $this->matchCollectionByTrailingSlashSupport($pathinfo.'/', true);

                return array_replace($parameters, $this->redirect($pathinfo.'/', isset($parameters['_route']) ? $parameters['_route'] : null));
            } catch (ResourceNotFoundException $e2) {
                throw $e;
            }
        }

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        // expression condition
        if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), ['context' => $this->context, 'request' => $this->request ?: $this->createRequest($pathinfo)])) {
            return [self::REQUIREMENT_MISMATCH, null];
        }

        // check HTTP scheme requirement
        $scheme = $this->context->getScheme();
        $schemes = $route->getSchemes();
        if ($schemes && !$route->hasScheme($scheme)) {
            return [self::ROUTE_MATCH, $this->redirect($pathinfo, $name, current($schemes))];
        }

        return [self::REQUIREMENT_MATCH, null];
    }

    protected function matchCollectionByTrailingSlashSupport($pathinfo, $trailingSlashSupport)
    {
        $this->allow = [];
        if ($ret = $this->matchCollection(rawurldecode($pathinfo), $this->routes, $trailingSlashSupport)) {
            return $ret;
        }

        if ('/' === $pathinfo && !$this->allow) {
            throw new NoConfigurationException();
        }

        throw 0 < \count($this->allow)
            ? new MethodNotAllowedException(array_unique($this->allow))
            : new ResourceNotFoundException(sprintf('No routes found for "%s".', $pathinfo));
    }
}
