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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\FooController;

class RouteTest extends TestCase
{
    /**
     * @dataProvider getValidParameters
     */
    public function testLoadFromAttribute(string $methodName, string $getter, mixed $expectedReturn)
    {
        $route = (new \ReflectionMethod(FooController::class, $methodName))->getAttributes(Route::class)[0]->newInstance();

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
            ['alias', 'getAliases', ['alias', 'completely_different_name']],
        ];
    }
}
