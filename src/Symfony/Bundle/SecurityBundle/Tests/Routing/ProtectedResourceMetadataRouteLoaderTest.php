<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Routing\ProtectedResourceMetadataRouteLoader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ProtectedResourceMetadataRouteLoaderTest extends TestCase
{
    public function testLoad()
    {
        $paths = [
            'main' => '/.well-known/oauth-protected-resource',
            'admin' => '/.well-known/oauth-protected-resource/admin',
        ];

        $loader = new ProtectedResourceMetadataRouteLoader($paths, 'parameterName');
        $collection = $loader();

        self::assertInstanceOf(RouteCollection::class, $collection);
        self::assertCount(2, $collection);

        $expected = static fn (string $path, string $firewallName): Route => (new Route($path, [
            '_controller' => 'security.authenticator.access_token.protected_resource_metadata_controller',
            'firewallName' => $firewallName,
        ]))->setMethods(['GET', 'OPTIONS']);

        self::assertEquals($expected('/.well-known/oauth-protected-resource', 'main'), $collection->get('_oauth_protected_resource_metadata_main'));
        self::assertEquals($expected('/.well-known/oauth-protected-resource/admin', 'admin'), $collection->get('_oauth_protected_resource_metadata_admin'));

        $resources = $collection->getResources();
        self::assertCount(1, $resources);

        $resource = reset($resources);
        self::assertInstanceOf(ContainerParametersResource::class, $resource);
        self::assertSame(['parameterName' => $paths], $resource->getParameters());
    }

    public function testLoadWithoutAnyConfiguredFirewall()
    {
        $collection = (new ProtectedResourceMetadataRouteLoader([], 'parameterName'))();

        self::assertCount(0, $collection);
    }

    public function testRejectsAPathSharedBetweenFirewalls()
    {
        $loader = new ProtectedResourceMetadataRouteLoader([
            'main' => '/.well-known/oauth-protected-resource',
            'admin' => '/.well-known/oauth-protected-resource',
        ], 'parameterName');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "main" and "admin" firewalls both serve their protected resource metadata at "/.well-known/oauth-protected-resource"; give each firewall a "resource" with its own path component, as described in RFC 9728, Section 3.1.');

        $loader();
    }
}
