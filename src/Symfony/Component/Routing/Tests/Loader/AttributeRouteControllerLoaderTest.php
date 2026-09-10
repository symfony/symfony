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
use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Tests\Loader\Fixtures\InvokableController;
use Symfony\Component\Routing\Tests\Loader\Fixtures\MethodActionControllers;

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
