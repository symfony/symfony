<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;
use Symfony\Component\HttpKernel\Command\ExpressionLintCommand;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExpressionLint\ExpressionsController;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExpressionLint\InvalidExpressionsController;

class ExpressionLintCommandTest extends TestCase
{
    public function testValidExpressions()
    {
        $collector = new ExpressionCollector([], [], ['app' => [
            'a + 1' => [['a'], ['the configuration']],
            'b + 1' => [null, []],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('All 2 expressions are valid.', $tester->getDisplay());
    }

    public function testValidAttributeExpressions()
    {
        $collector = new ExpressionCollector([ExpressionsController::class], ['app' => [Cache::class => ['if' => [true, ['request', 'args', 'this']], 'etag' => [true, ['request', 'args', 'this']]]]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('All 2 expressions are valid.', $tester->getDisplay());
    }

    public function testInvalidExpressions()
    {
        $collector = new ExpressionCollector([InvalidExpressionsController::class], ['app' => [Cache::class => ['if' => [true, null], 'etag' => [true, ['request', 'args', 'this']]]]], ['app' => [
            'a + 1' => [['b'], ['the configuration', 'another place']],
            'a +' => [null, []],
            'unknown()' => [null, ['the configuration']],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute([]));

        $display = preg_replace('/ +/', ' ', $tester->getDisplay(true));
        $controller = InvalidExpressionsController::class;

        $this->assertStringContainsString(\sprintf('ERROR in the "if" option of #[Cache] on "%s::invalid()"', $controller), $display);
        $this->assertStringContainsString('>> Unexpected token "name" of value "and" around position 12 for expression `this.ready and`.', $display);
        $this->assertStringContainsString(\sprintf('ERROR in the "etag" option of #[Cache] on "%s::invalid()"', $controller), $display);
        $this->assertStringContainsString('>> Variable "id" is not valid around position 1 for expression `id`.', $display);
        $this->assertStringContainsString('ERROR in the configuration and another place', $display);
        $this->assertStringContainsString('>> Variable "a" is not valid around position 1 for expression `a + 1`.', $display);
        $this->assertStringContainsString('ERROR in the expressions listed for the "app" service', $display);
        $this->assertStringContainsString('>> The function "unknown" does not exist', $display);
        $this->assertStringContainsString(\sprintf('ERROR in #[Cache] on "%s::broken()"', $controller), $display);
        $this->assertStringContainsString('$maxage', $display);
        $this->assertStringContainsString('Found 6 errors while linting 5 expressions.', $display);
    }
}
