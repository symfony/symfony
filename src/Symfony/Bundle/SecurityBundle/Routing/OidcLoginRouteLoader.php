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
 * returning a 404 from the router, a route for each start path, which redirects
 * to the provider so that e.g. a login page can link to it, and a route for each
 * back-channel logout path, where the provider pushes its logout tokens.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginRouteLoader
{
    /**
     * @param array<string, string> $callbackUris                        Callback URIs indexed by the corresponding firewall name
     * @param string                $callbackUrisParameterName           Name of the container parameter containing {@see $callbackUris} value
     * @param array<string, string> $startPaths                          Start paths indexed by the corresponding firewall name
     * @param string                $startPathsParameterName             Name of the container parameter containing {@see $startPaths} value
     * @param array<string, string> $backChannelLogoutPaths              Back-channel logout paths indexed by the corresponding firewall name
     * @param string                $backChannelLogoutPathsParameterName Name of the container parameter containing {@see $backChannelLogoutPaths} value
     */
    public function __construct(
        private readonly array $callbackUris,
        private readonly string $callbackUrisParameterName,
        private readonly array $startPaths,
        private readonly string $startPathsParameterName,
        private readonly array $backChannelLogoutPaths = [],
        private readonly string $backChannelLogoutPathsParameterName = 'security.oidc_login.backchannel_logout_paths',
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addResource(new ContainerParametersResource([
            $this->callbackUrisParameterName => $this->callbackUris,
            $this->startPathsParameterName => $this->startPaths,
            $this->backChannelLogoutPathsParameterName => $this->backChannelLogoutPaths,
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

        $backChannelLogoutFirewalls = [];
        foreach ($this->backChannelLogoutPaths as $firewallName => $logoutPath) {
            // the route carries the firewall name, as a start path does, and for the same
            // reason: the provider of one firewall must not end the sessions of the other
            if (isset($backChannelLogoutFirewalls[$logoutPath])) {
                throw new \LogicException(\sprintf('The "%s" and "%s" firewalls both use "%s" as their oidc_login back-channel logout path; give each firewall its own.', $backChannelLogoutFirewalls[$logoutPath], $firewallName, $logoutPath));
            }

            $backChannelLogoutFirewalls[$logoutPath] = $firewallName;
            // the provider posts the logout token (Back-Channel Logout 1.0, Section 2.5), so
            // anything else is answered by the router rather than by the endpoint
            $collection->add('_oidc_login_backchannel_logout_'.$firewallName, (new Route($logoutPath, ['_controller' => 'security.authenticator.oidc_login.backchannel_logout_controller', 'firewallName' => $firewallName]))->setMethods(['POST']));
        }

        return $collection;
    }
}
