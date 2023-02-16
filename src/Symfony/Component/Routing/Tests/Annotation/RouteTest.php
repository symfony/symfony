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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\FooController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\FooController as FooAttributesController;

class RouteTest extends TestCase
{
    use ExpectDeprecationTrait;

    private function getMethodAnnotation(string $method, bool $attributes): Route
    {
        $class = $attributes ? FooAttributesController::class : FooController::class;
        $reflection = new \ReflectionMethod($class, $method);

        if ($attributes) {
            $attributes = $reflection->getAttributes(Route::class);
            $route = $attributes[0]->newInstance();
        } else {
            $reader = new AnnotationReader();
            $route = $reader->getMethodAnnotation($reflection, Route::class);
        }

        if (!$route instanceof Route) {
            throw new \Exception('Can\'t parse annotation');
        }

        return $route;
    }

    public static function provideDeprecationArrayAsFirstArgument()
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
            ['value', '/Blog', 'getPath'],
            ['value', ['nl' => '/hier', 'en' => '/here'], 'getLocalizedPaths'],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideDeprecationArrayAsFirstArgument
     */
    public function testDeprecationArrayAsFirstArgument(string $parameter, $value, string $getter)
    {
        $this->expectDeprecation('Since symfony/routing 5.3: Passing an array as first argument to "Symfony\Component\Routing\Annotation\Route::__construct" is deprecated. Use named arguments instead.');

        $route = new Route([$parameter => $value]);
        $this->assertEquals($route->$getter(), $value);
    }

    /**
     * @requires PHP 8
     *
     * @dataProvider getValidParameters
     */
    public function testLoadFromAttribute(string $methodName, string $getter, $expectedReturn)
    {
        $route = $this->getMethodAnnotation($methodName, true);
        $this->assertEquals($route->$getter(), $expectedReturn);
    }

    /**
     * @dataProvider getValidParameters
     */
    public function testLoadFromDoctrineAnnotation(string $methodName, string $getter, $expectedReturn)
    {
        $route = $this->getMethodAnnotation($methodName, false);
        $this->assertEquals($route->$getter(), $expectedReturn);
    }

    public static function getValidParameters(): iterable
    {
        return [
            ['simplePath', 'getPath', '/Blog'],
            ['localized', 'getLocalizedPaths', ['nl' => '/hier', 'en' => '/here']],
            ['requirements', 'getRequirements', ['locale' => 'en']],
            ['options', 'getOptions', ['compiler_class' => 'RouteCompiler']],
            ['name', 'getName', 'blog_index'],
            ['defaults', 'getDefaults', ['_controller' => 'MyBlogBundle:Blog:index']],
            ['schemes', 'getSchemes', ['https']],
            ['methods', 'getMethods', ['GET', 'POST']],
            ['host', 'getHost', '{locale}.example.com'],
            ['condition', 'getCondition', 'context.getMethod() == \'GET\''],
        ];
    }
}
