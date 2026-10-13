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
use Symfony\Bundle\SecurityBundle\Routing\LogoutRouteLoader;
use Symfony\Bundle\SecurityBundle\Routing\OidcLoginRouteLoader;
use Symfony\Bundle\SecurityBundle\Routing\ProtectedResourceMetadataRouteLoader;
use Symfony\Bundle\SecurityBundle\Routing\SecurityRouteLoader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;

class SecurityRouteLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new SecurityRouteLoader([
            new LogoutRouteLoader(['main' => '/logout', 'admin' => '/logout'], 'security.logout_uris'),
            new OidcLoginRouteLoader(['main' => '/oidc/callback'], 'security.oidc_login.callback_uris', ['main' => '/oidc/start'], 'security.oidc_login.start_paths', [], 'security.oidc_login.backchannel_logout_paths'),
            new ProtectedResourceMetadataRouteLoader(['api' => '/.well-known/oauth-protected-resource'], 'security.access_token.resource_metadata_paths'),
        ]);
        $collection = $loader();

        self::assertSame(['_logout_main', '_oidc_login_callback_main', '_oidc_login_start_main', '_oauth_protected_resource_metadata_api'], array_keys($collection->all()));
        self::assertEquals(new Route('/logout'), $collection->get('_logout_main'));
        self::assertSame('_logout_main', $collection->getAlias('_logout_admin')->getId());

        $parameterNames = [];
        foreach ($collection->getResources() as $resource) {
            self::assertInstanceOf(ContainerParametersResource::class, $resource);
            array_push($parameterNames, ...array_keys($resource->getParameters()));
        }
        self::assertSame(['security.logout_uris', 'security.oidc_login.callback_uris', 'security.oidc_login.start_paths', 'security.oidc_login.backchannel_logout_paths', 'security.access_token.resource_metadata_paths'], $parameterNames);
    }
}
