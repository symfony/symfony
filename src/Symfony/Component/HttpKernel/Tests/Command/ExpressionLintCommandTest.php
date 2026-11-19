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

use PHPUnit\Framework\Attributes\TestWith;
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
        $this->assertSame([['source' => 'the configuration', 'expression' => 'a + 1', 'valid' => true]], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testJsonInvalidExpressions()
    {
        $collector = new ExpressionCollector([InvalidExpressionsController::class], ['app' => [Cache::class => ['if' => [true, null]]]], ['app' => [
            'unknown()' => [null, ['configuration <error>source</error>']],
            'a + 1' => [['a'], ['valid source']],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute(['--format' => 'json'], ['decorated' => true]));
        $results = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(4, $results);
        $this->assertSame('configuration <error>source</error>', $results[1]['source']);
        $this->assertStringContainsString('The function "unknown" does not exist', $results[1]['message']);
        $this->assertSame('this.ready and', $results[0]['expression']);
        $this->assertFalse($results[0]['valid']);
        $this->assertSame('unknown()', $results[1]['expression']);
        $this->assertFalse($results[1]['valid']);
        $this->assertSame(['source' => 'valid source', 'expression' => 'a + 1', 'valid' => true], $results[2]);
        $this->assertStringContainsString('::broken()', $results[3]['source']);
        $this->assertNull($results[3]['expression']);
        $this->assertFalse($results[3]['valid']);
        $this->assertStringContainsString('$maxage', $results[3]['message']);
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

    public function testJsonValidAttributeExpressions()
    {
        $collector = new ExpressionCollector([ExpressionsController::class], ['app' => [Cache::class => ['if' => [true, ['request', 'args', 'this']], 'etag' => [true, ['request', 'args', 'this']]]]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(0, $tester->execute(['--format' => 'json']));
        $results = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(['request.isMethodSafe()', 'args["id"]'], array_column($results, 'expression'));
        $this->assertSame([true, true], array_column($results, 'valid'));
        $this->assertStringContainsString('::show()', $results[1]['source']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testGithubFormat(bool $decorated)
    {
        $collector = new ExpressionCollector([InvalidExpressionsController::class], ['app' => [Cache::class => ['if' => [true, null]]]], ['app' => [
            'unknown()' => [null, ["configuration <error>source</error>%,\r\n"]],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute(['--format' => 'github'], ['decorated' => $decorated]));
        $display = $tester->getDisplay();
        $this->assertSame(3, substr_count($display, '::error::'));
        $this->assertStringContainsString('::error::configuration <error>source</error>%25,%0D%0A: The function "unknown" does not exist', $display);
        $this->assertStringContainsString('::broken()', $display);
        $this->assertStringContainsString('Found 3 errors while linting 2 expressions.', preg_replace('/ +/', ' ', $display));
    }

    #[TestWith([null, true])]
    #[TestWith(['txt', false])]
    #[TestWith(['json', false])]
    public function testGithubActionsDefaultFormat(?string $format, bool $annotations)
    {
        $previous = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS=true');

        try {
            $collector = new ExpressionCollector([], [], ['app' => ['unknown()' => [null, ['configuration']]]]);
            $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

            $this->assertSame(1, $tester->execute(null === $format ? [] : ['--format' => $format]));
            $this->assertSame($annotations, str_contains($tester->getDisplay(), '::error::'));
            if ('json' === $format) {
                $this->assertFalse(json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR)[0]['valid']);
            }
        } finally {
            putenv(false === $previous ? 'GITHUB_ACTIONS' : 'GITHUB_ACTIONS='.$previous);
        }
    }

    public function testTextFormatIsDefaultOutsideGithubActions()
    {
        $previous = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS');

        try {
            $collector = new ExpressionCollector([], [], ['app' => ['unknown()' => [null, ['configuration']]]]);
            $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

            $this->assertSame(1, $tester->execute([]));
            $this->assertStringNotContainsString('::error::', $tester->getDisplay());
            $this->assertStringContainsString('ERROR', $tester->getDisplay());
        } finally {
            putenv(false === $previous ? 'GITHUB_ACTIONS' : 'GITHUB_ACTIONS='.$previous);
        }
    }

    public function testGithubFormatWithoutErrors()
    {
        $collector = new ExpressionCollector([], [], ['app' => ['a + 1' => [['a'], ['configuration']]]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(0, $tester->execute(['--format' => 'github']));
        $this->assertStringNotContainsString('::error::', $tester->getDisplay());
        $this->assertStringContainsString('All 1 expressions are valid.', $tester->getDisplay());
    }

    public function testUnsupportedFormat()
    {
        $tester = new CommandTester(new ExpressionLintCommand([], new ExpressionCollector([], [])));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "txt", "json" and "github".');
        $tester->execute(['--format' => 'xml']);
    }

    public function testCompleteFormat()
    {
        $tester = new CommandCompletionTester(new ExpressionLintCommand([], new ExpressionCollector([], [])));

        $this->assertSame(['txt', 'json', 'github'], $tester->complete(['--format', '']));
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

    public function testInvalidExpressionsWithMarkup()
    {
        $collector = new ExpressionCollector([], [], ['app' => [
            'foo("</info>x")' => [null, ['the <comment>configuration</comment>']],
        ]]);
        $tester = new CommandTester(new ExpressionLintCommand(['app' => new ExpressionLanguage()], $collector));

        $this->assertSame(1, $tester->execute([], ['decorated' => false]));

        $display = preg_replace('/ +/', ' ', $tester->getDisplay(true));

        $this->assertStringContainsString('ERROR in the <comment>configuration</comment>', $display);
        $this->assertStringContainsString('>> The function "foo" does not exist around position 1 for expression `foo("</info>x")`.', $display);
    }
}
