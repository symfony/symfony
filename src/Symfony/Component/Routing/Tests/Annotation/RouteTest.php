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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Routing\Annotation\Route;

class RouteTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testInvalidRouteParameter()
    {
        $this->expectException(\BadMethodCallException::class);
        new Route(['foo' => 'bar']);
    }

    /**
     * @group legacy
     */
    public function testTryingToSetLocalesDirectly()
    {
        $this->expectException(\BadMethodCallException::class);
        new Route(['locales' => ['nl' => 'bar']]);
    }

    /**
     * @requires PHP 8
     * @dataProvider getValidParameters
     */
    public function testRouteParameters(string $parameter, $value, string $getter)
    {
        $route = new Route(...[$parameter => $value]);
        $this->assertEquals($route->$getter(), $value);
    }

    /**
     * @group legacy
     * @dataProvider getLegacyValidParameters
     */
    public function testLegacyRouteParameters(string $parameter, $value, string $getter)
    {
        $this->expectDeprecation('Since symfony/routing 5.3: Passing an array as first argument to "Symfony\Component\Routing\Annotation\Route::__construct" is deprecated. Use named arguments instead.');

        $route = new Route([$parameter => $value]);
        $this->assertEquals($route->$getter(), $value);
    }

    public function getValidParameters(): iterable
    {
        return [
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

    public function getLegacyValidParameters(): iterable
    {
        yield from $this->getValidParameters();

        yield ['value', '/Blog', 'getPath'];
        yield ['value', ['nl' => '/hier', 'en' => '/here'], 'getLocalizedPaths'];
    }
}
