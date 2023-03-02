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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\FooController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\FooController as FooAttributesController;

class RouteTest extends TestCase
{
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

    /**
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
