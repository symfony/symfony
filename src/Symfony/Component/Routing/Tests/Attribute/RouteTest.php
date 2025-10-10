<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\FooController;

class RouteTest extends TestCase
{
    #[DataProvider('getValidParameters')]
    public function testLoadFromAttribute(string $methodName, string $property, mixed $expectedReturn)
    {
        $route = (new \ReflectionMethod(FooController::class, $methodName))->getAttributes(Route::class)[0]->newInstance();

        $this->assertEquals($route->$property, $expectedReturn);
    }

    public static function getValidParameters(): iterable
    {
        return [
            ['simplePath', 'path', '/Blog'],
            ['localized', 'path', ['nl' => '/hier', 'en' => '/here']],
            ['requirements', 'requirements', ['locale' => 'en']],
            ['options', 'options', ['compiler_class' => 'RouteCompiler']],
            ['name', 'name', 'blog_index'],
            ['defaults', 'defaults', ['_controller' => 'MyBlogBundle:Blog:index']],
            ['schemes', 'schemes', ['https']],
            ['methods', 'methods', ['GET', 'POST']],
            ['host', 'host', '{locale}.example.com'],
            ['condition', 'condition', 'context.getMethod() == \'GET\''],
            ['alias', 'aliases', ['alias', 'completely_different_name']],
        ];
    }
}
