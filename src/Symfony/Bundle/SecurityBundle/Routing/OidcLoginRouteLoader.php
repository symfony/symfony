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

/**
 * Registers a route for each oidc_login firewall callback path, so the provider's
 * redirect lands on a matched route and is handled by the firewall instead of
 * returning a 404 from the router.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginRouteLoader
{
    /**
     * @param array<string, string> $callbackUris  Callback URIs indexed by the corresponding firewall name
     * @param string                $parameterName Name of the container parameter containing {@see $callbackUris} value
     */
    public function __construct(
        private readonly array $callbackUris,
        private readonly string $parameterName,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addResource(new ContainerParametersResource([$this->parameterName => $this->callbackUris]));

        $routeNames = [];
        foreach ($this->callbackUris as $firewallName => $callbackPath) {
            $routeName = '_oidc_login_callback_'.$firewallName;

            if (isset($routeNames[$callbackPath])) {
                $collection->addAlias($routeName, $routeNames[$callbackPath]);
            } else {
                $routeNames[$callbackPath] = $routeName;
                $collection->add($routeName, new Route($callbackPath));
            }
        }

        return $collection;
    }
}
