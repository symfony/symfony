<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class OidcLoginRouteLoaderTest extends AbstractWebTestCase
{
    public function testRouteLoaderCanBeImportedWithoutOidcLoginFirewall()
    {
        $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config.yml']);

        $routes = static::getContainer()->get('router')->getRouteCollection();

        $this->assertSame([], array_filter(array_keys($routes->all()), static fn (string $name) => str_starts_with($name, '_oidc_login_callback_')));
        $this->assertNotNull($routes->get('_logout_main'));
    }

    public function testRouteLoaderDeclaresTheCallbackRoute()
    {
        $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc.yml']);

        $routes = static::getContainer()->get('router')->getRouteCollection();

        $this->assertSame('/oidc/callback', $routes->get('_oidc_login_callback_oidc')?->getPath());
    }
}
