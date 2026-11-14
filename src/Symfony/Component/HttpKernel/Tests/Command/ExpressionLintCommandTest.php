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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;
use Symfony\Component\HttpKernel\Command\ExpressionLintCommand;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExpressionLint\ExpressionsController;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExpressionLint\InvalidExpressionsController;

class ExpressionLintCommandTest extends TestCase
{
    public function testJsonValidExpressions()
    {
        $collector = new ExpressionCollector([], [], ['app' => ['a + 1' => [['a'], ['the configuration']]]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(0, $tester->execute(['--format' => 'json'], ['decorated' => true]));
        $this->assertSame([], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testJsonInvalidExpressions()
    {
        $collector = new ExpressionCollector([InvalidExpressionsController::class], ['app' => [Cache::class => ['if' => [true, null]]]], ['app' => [
            'unknown()' => [null, ['configuration <error>source</error>']],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute(['--format' => 'json'], ['decorated' => true]));
        $errors = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(3, $errors);
        $this->assertSame('configuration <error>source</error>', $errors[1]['source']);
        $this->assertStringContainsString('The function "unknown" does not exist', $errors[1]['message']);
        $this->assertStringContainsString('::broken()', $errors[2]['source']);
        $this->assertStringContainsString('$maxage', $errors[2]['message']);
    }

    public function testJsonWithInvalidUtf8Source()
    {
        $collector = new ExpressionCollector([], [], ['app' => ['unknown()' => [null, ["source \xFF"]]]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute(['--format' => 'json']));
        $errors = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame("source \u{FFFD}", $errors[0]['source']);
    }

    public function testJsonWithoutExpressions()
    {
        $tester = new CommandTester(new ExpressionLintCommand([], new ExpressionCollector([], [])));

        $this->assertSame(0, $tester->execute(['--format' => 'json']));
        $this->assertSame([], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testUnsupportedFormat()
    {
        $tester = new CommandTester(new ExpressionLintCommand([], new ExpressionCollector([], [])));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "txt" and "json".');
        $tester->execute(['--format' => 'xml']);
    }

    public function testCompleteFormat()
    {
        $tester = new CommandCompletionTester(new ExpressionLintCommand([], new ExpressionCollector([], [])));

        $this->assertSame(['txt', 'json'], $tester->complete(['--format', '']));
    }

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
