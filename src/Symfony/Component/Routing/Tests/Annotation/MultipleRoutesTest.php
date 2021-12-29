<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MultipleRoutesController;

class MultipleRoutesTest extends TestCase
{
    public function testAlias(): void
    {
        $loader = new AnnotatedRouteControllerLoader(new AnnotationReader());
        $collection = $loader->load(MultipleRoutesController::class);

        $this->assertSame(
            [
                '/MainRoute2/SubPath',
                '/RouteAlias2/SubPath',
                '/MainRoute2/SubAlias',
                '/RouteAlias2/SubAlias',
            ],
            array_map(
                static fn (Route $route) => $route->getPath(),
                array_values(iterator_to_array($collection)),
            ),
        );
    }
}
