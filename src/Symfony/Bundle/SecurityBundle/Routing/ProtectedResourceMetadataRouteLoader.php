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
 * Registers a route serving the RFC 9728 protected resource metadata document of each
 * "access_token" firewall that configures one, at the well-known path derived from its
 * resource identifier.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class ProtectedResourceMetadataRouteLoader
{
    /**
     * @param array<string, string> $paths         Well-known paths indexed by the corresponding firewall name
     * @param string                $parameterName Name of the container parameter containing {@see $paths} value
     */
    public function __construct(
        private readonly array $paths,
        private readonly string $parameterName,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addResource(new ContainerParametersResource([$this->parameterName => $this->paths]));

        $firewalls = [];
        foreach ($this->paths as $firewallName => $path) {
            // the route carries the firewall name, so two firewalls cannot share a path:
            // one would serve the metadata document of the other
            if (isset($firewalls[$path])) {
                throw new \LogicException(\sprintf('The "%s" and "%s" firewalls both serve their protected resource metadata at "%s"; give each firewall a "resource" with its own path component, as described in RFC 9728, Section 3.1.', $firewalls[$path], $firewallName, $path));
            }

            $firewalls[$path] = $firewallName;
            $collection->add('_oauth_protected_resource_metadata_'.$firewallName, (new Route($path, [
                '_controller' => 'security.authenticator.access_token.protected_resource_metadata_controller',
                'firewallName' => $firewallName,
            ]))->setMethods(['GET']));
        }

        return $collection;
    }
}
