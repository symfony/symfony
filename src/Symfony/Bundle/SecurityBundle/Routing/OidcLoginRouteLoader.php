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
 * returning a 404 from the router, and a route for each start path, which redirects
 * to the provider so that e.g. a login page can link to it.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginRouteLoader
{
    /**
     * @param array<string, string> $callbackUris              Callback URIs indexed by the corresponding firewall name
     * @param string                $callbackUrisParameterName Name of the container parameter containing {@see $callbackUris} value
     * @param array<string, string> $startPaths                Start paths indexed by the corresponding firewall name
     * @param string                $startPathsParameterName   Name of the container parameter containing {@see $startPaths} value
     */
    public function __construct(
        private readonly array $callbackUris,
        private readonly string $callbackUrisParameterName,
        private readonly array $startPaths,
        private readonly string $startPathsParameterName,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addResource(new ContainerParametersResource([
            $this->callbackUrisParameterName => $this->callbackUris,
            $this->startPathsParameterName => $this->startPaths,
        ]));

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

        $startFirewalls = [];
        foreach ($this->startPaths as $firewallName => $startPath) {
            // the route carries the firewall name, so unlike a callback path, a start
            // path cannot be shared: the route of one firewall would start the other's flow
            if (isset($startFirewalls[$startPath])) {
                throw new \LogicException(\sprintf('The "%s" and "%s" firewalls both use "%s" as their oidc_login "start_path"; give each firewall its own.', $startFirewalls[$startPath], $firewallName, $startPath));
            }

            $startFirewalls[$startPath] = $firewallName;
            $collection->add('_oidc_login_start_'.$firewallName, new Route($startPath, ['_controller' => 'security.authenticator.oidc_login.start_controller', 'firewallName' => $firewallName]));
        }

        return $collection;
    }
}
