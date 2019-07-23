<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Loader\DependencyInjection\ServiceRouterLoader;

class ServiceRouterLoaderTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Component\Routing\Loader\DependencyInjection\ServiceRouterLoader" class is deprecated since Symfony 4.4, use "Symfony\Component\Routing\Loader\ContainerLoader" instead.
     * @expectedDeprecation The "Symfony\Component\Routing\Loader\ObjectRouteLoader" class is deprecated since Symfony 4.4, use "Symfony\Component\Routing\Loader\ObjectLoader" instead.
     */
    public function testDeprecationWarning()
    {
        new ServiceRouterLoader(new Container());
    }
}
