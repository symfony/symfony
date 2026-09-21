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
use Symfony\Bundle\SecurityBundle\Routing\OidcLoginRouteLoader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class OidcLoginRouteLoaderTest extends TestCase
{
    public function testLoad()
    {
        $callbackUris = [
            'main' => '/callback',
            'admin' => '/callback',
        ];
        $startPaths = [
            'main' => '/start',
            'admin' => '/admin/start',
        ];

        $backChannelLogoutPaths = [
            'main' => '/oidc/backchannel-logout',
        ];

        $loader = new OidcLoginRouteLoader($callbackUris, 'callbackParameterName', $startPaths, 'startParameterName', $backChannelLogoutPaths, 'backChannelLogoutParameterName');
        $collection = $loader();

        self::assertInstanceOf(RouteCollection::class, $collection);
        self::assertCount(4, $collection);
        self::assertEquals(new Route('/callback'), $collection->get('_oidc_login_callback_main'));
        self::assertCount(1, $collection->getAliases());
        self::assertEquals('_oidc_login_callback_main', $collection->getAlias('_oidc_login_callback_admin')->getId());
        self::assertEquals(new Route('/start', ['_controller' => 'security.authenticator.oidc_login.start_controller', 'firewallName' => 'main']), $collection->get('_oidc_login_start_main'));
        self::assertEquals(new Route('/admin/start', ['_controller' => 'security.authenticator.oidc_login.start_controller', 'firewallName' => 'admin']), $collection->get('_oidc_login_start_admin'));
        self::assertEquals((new Route('/oidc/backchannel-logout', ['_controller' => 'security.authenticator.oidc_login.backchannel_logout_controller', 'firewallName' => 'main']))->setMethods(['POST']), $collection->get('_oidc_login_backchannel_logout_main'));

        $resources = $collection->getResources();
        self::assertCount(1, $resources);

        $resource = reset($resources);
        self::assertInstanceOf(ContainerParametersResource::class, $resource);
        self::assertSame(['callbackParameterName' => $callbackUris, 'startParameterName' => $startPaths, 'backChannelLogoutParameterName' => $backChannelLogoutPaths], $resource->getParameters());
    }

    public function testRejectsAStartPathSharedBetweenFirewalls()
    {
        $loader = new OidcLoginRouteLoader([], 'callbackParameterName', ['main' => '/start', 'admin' => '/start'], 'startParameterName');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "main" and "admin" firewalls both use "/start" as their oidc_login "start_path"; give each firewall its own.');

        $loader();
    }

    public function testRejectsABackChannelLogoutPathSharedBetweenFirewalls()
    {
        $loader = new OidcLoginRouteLoader([], 'callbackParameterName', [], 'startParameterName', ['main' => '/oidc/logout', 'admin' => '/oidc/logout'], 'backChannelLogoutParameterName');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "main" and "admin" firewalls both use "/oidc/logout" as their oidc_login back-channel logout path; give each firewall its own.');

        $loader();
    }
}
