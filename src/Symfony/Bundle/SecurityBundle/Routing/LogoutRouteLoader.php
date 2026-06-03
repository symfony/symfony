<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Routing;

use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LogoutRouteLoader
{
    /**
     * @param array<string, string> $logoutUris    Logout URIs indexed by the corresponding firewall name
     * @param string                $parameterName Name of the container parameter containing {@see $logoutUris} value
     */
    public function __construct(
        private readonly array $logoutUris,
        private readonly string $parameterName,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addResource(new ContainerParametersResource([$this->parameterName => $this->logoutUris]));

        $routeNames = [];
        foreach ($this->logoutUris as $firewallName => $logoutPath) {
            $routeName = '_logout_'.$firewallName;

            if (isset($routeNames[$logoutPath])) {
                $collection->addAlias($routeName, $routeNames[$logoutPath]);
            } else {
                $routeNames[$logoutPath] = $routeName;
                $collection->add($routeName, new Route($logoutPath));
            }
        }

        return $collection;
    }
}
