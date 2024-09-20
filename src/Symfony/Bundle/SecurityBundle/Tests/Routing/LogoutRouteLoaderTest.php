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
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class LogoutRouteLoaderTest extends TestCase
{
    public function testLoad()
    {
        $logoutPaths = [
            'main' => '/logout',
            'admin' => '/logout',
        ];

        $loader = new LogoutRouteLoader($logoutPaths, 'parameterName');
        $collection = $loader();

        self::assertInstanceOf(RouteCollection::class, $collection);
        self::assertCount(1, $collection);
        self::assertEquals(new Route('/logout'), $collection->get('_logout_main'));
        self::assertCount(1, $collection->getAliases());
        self::assertEquals('_logout_main', $collection->getAlias('_logout_admin')->getId());

        $resources = $collection->getResources();
        self::assertCount(1, $resources);

        $resource = reset($resources);
        self::assertInstanceOf(ContainerParametersResource::class, $resource);
        self::assertSame(['parameterName' => $logoutPaths], $resource->getParameters());
    }
}
