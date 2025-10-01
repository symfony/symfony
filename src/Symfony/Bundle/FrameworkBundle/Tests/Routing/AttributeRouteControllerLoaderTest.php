<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\FrameworkBundle\Tests\Routing\Fixtures\InvokableController;
use Symfony\Bundle\FrameworkBundle\Tests\Routing\Fixtures\MethodActionControllers;

class AttributeRouteControllerLoaderTest extends TestCase
{
    public function testConfigureRouteSetsControllerForInvokable()
    {
        $loader = new AttributeRouteControllerLoader();
        $collection = $loader->load(InvokableController::class);

        $route = $collection->get('lol');
        $this->assertSame(InvokableController::class, $route->getDefault('_controller'));
    }

    public function testConfigureRouteSetsControllerForMethod()
    {
        $loader = new AttributeRouteControllerLoader();
        $collection = $loader->load(MethodActionControllers::class);

        $put = $collection->get('put');
        $post = $collection->get('post');

        $this->assertSame(MethodActionControllers::class.'::put', $put->getDefault('_controller'));
        $this->assertSame(MethodActionControllers::class.'::post', $post->getDefault('_controller'));
    }
}
