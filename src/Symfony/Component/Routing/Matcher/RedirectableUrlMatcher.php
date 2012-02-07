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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
abstract class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    private $trailingSlashTest = false;

    /**
     * @see UrlMatcher::match()
     *
     * @api
     */
    public function match($pathinfo)
    {
        try {
            $parameters = parent::match($pathinfo);
        } catch (ResourceNotFoundException $e) {
            if ('/' === substr($pathinfo, -1)) {
                throw $e;
            }

            // try with a / at the end
            $this->trailingSlashTest = true;

            return $this->match($pathinfo.'/');
        }

        if ($this->trailingSlashTest) {
            $this->trailingSlashTest = false;

            return $this->redirect($pathinfo, null);
        }

        return $parameters;
    }

    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        if (self::PROCESS_NEXT_ROUTE === parent::handleRouteRequirements($pathinfo, $name, $route)) {
            return self::PROCESS_NEXT_ROUTE;
        }

        // check HTTP scheme requirement
        if ($scheme = $route->getRequirement('_scheme') && $this->context->getScheme() !== $scheme) {
            return $this->redirect($pathinfo, $name, $scheme);
        }

        return self::PROCESS_CURRENT_ROUTE;
    }

}
