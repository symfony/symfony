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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Annotation\Route;

class RouteTest extends TestCase
{
    public function testInvalidRouteParameter()
    {
        $this->expectException('BadMethodCallException');
        new Route(['foo' => 'bar']);
    }

    /**
     * @dataProvider getValidParameters
     */
    public function testRouteParameters($parameter, $value, $getter)
    {
        $route = new Route([$parameter => $value]);
        $this->assertEquals($route->$getter(), $value);
    }

    public function getValidParameters()
    {
        return [
            ['value', '/Blog', 'getPath'],
            ['requirements', ['locale' => 'en'], 'getRequirements'],
            ['options', ['compiler_class' => 'RouteCompiler'], 'getOptions'],
            ['name', 'blog_index', 'getName'],
            ['defaults', ['_controller' => 'MyBlogBundle:Blog:index'], 'getDefaults'],
            ['schemes', ['https'], 'getSchemes'],
            ['methods', ['GET', 'POST'], 'getMethods'],
            ['host', '{locale}.example.com', 'getHost'],
            ['condition', 'context.getMethod() == "GET"', 'getCondition'],
        ];
    }
}
