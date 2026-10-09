<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\CacheWarmer\CollectedExpression;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExpressionsController;

class ExpressionCollectorTest extends TestCase
{
    public function testGetAttributeExpressions()
    {
        $collector = new ExpressionCollector([ExpressionsController::class], [
            'cache' => [Cache::class => ['if' => [true, null], 'lastModified' => [true, null], 'etag' => [true, ['args']]]],
            'query' => [MapQueryString::class => ['validationGroups' => [false, ['request', 'args', 'this']]]],
        ]);

        $controller = ExpressionsController::class;
        $this->assertEquals([
            new CollectedExpression('this.lastModified', null, \sprintf('the "lastModified" option of #[Cache] on "%s"', $controller)),
            new CollectedExpression('request.isMethodSafe()', null, \sprintf('the "if" option of #[Cache] on "%s::show()"', $controller)),
            new CollectedExpression('args["id"]', ['args'], \sprintf('the "etag" option of #[Cache] on "%s::show()"', $controller)),
        ], iterator_to_array($collector->getAttributeExpressions('cache'), false));
        $this->assertEquals([
            new CollectedExpression(new Expression('"Default"'), ['request', 'args', 'this'], \sprintf('the "validationGroups" option of #[MapQueryString] on the "$query" argument of "%s::show()"', $controller)),
        ], iterator_to_array($collector->getAttributeExpressions('query'), false));
        $this->assertSame([], iterator_to_array($collector->getAttributeExpressions('other'), false));
    }

    public function testStringsAreNotExpressionsUnlessDeclared()
    {
        $collector = new ExpressionCollector([ExpressionsController::class], ['cache' => [Cache::class => ['if' => [false, null]]]]);

        $this->assertSame([], iterator_to_array($collector->getAttributeExpressions('cache'), false));
    }

    public function testGetListedExpressions()
    {
        $collector = new ExpressionCollector([], [], ['app' => [
            'request.isSecure()' => [['request'], ['the configuration', 'the defaults']],
            '1' => [null, []],
        ]]);

        $this->assertEquals([
            new CollectedExpression('request.isSecure()', ['request'], 'the configuration and the defaults'),
            new CollectedExpression('1', null, 'the expressions listed for the "app" service'),
        ], iterator_to_array($collector->getListedExpressions('app'), false));
        $this->assertSame([], iterator_to_array($collector->getListedExpressions('other'), false));
    }

    public function testGetErrorsReportsTheAttributesHoldingExpressionsThatCannotBeInstantiated()
    {
        $collector = new ExpressionCollector([ExpressionsController::class], ['cache' => [Cache::class => ['etag' => [true, null]]]]);
        iterator_to_array($collector->getAttributeExpressions('cache'), false);

        $errors = [];
        foreach ($collector->getErrors() as $source => $error) {
            $errors[] = [$source, $error];
        }

        $this->assertCount(1, $errors);
        $this->assertSame(\sprintf('#[Cache] on "%s::invalid()"', ExpressionsController::class), $errors[0][0]);
        $this->assertStringContainsString('$maxage', $errors[0][1]->getMessage());

        $this->assertSame([], iterator_to_array((new ExpressionCollector([ExpressionsController::class], ['query' => [MapQueryString::class => ['validationGroups' => [false, null]]]]))->getErrors(), false));
    }
}
