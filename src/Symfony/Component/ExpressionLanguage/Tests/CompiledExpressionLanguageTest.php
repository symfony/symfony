<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class CompiledExpressionLanguageTest extends TestCase
{
    private array $compiledFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->compiledFiles as $file) {
            @unlink($file);
        }
    }

    #[DataProvider('provideCompiledExpressions')]
    public function testEvaluateCompiledExpression(string $expression, array $values, mixed $expected)
    {
        $file = $this->dumpCompiled([$expression]);

        $this->assertSame($expected, self::createLanguage($file)->evaluate($expression, $values));
    }

    public static function provideCompiledExpressions(): iterable
    {
        $object = new class {
            public int $count = 3;

            public function add(int $value): int
            {
                return $this->count + $value;
            }
        };

        yield ['twice(a) + b', ['a' => 1, 'b' => 2], 4];
        yield ['twice(this.count)', ['this' => $object], 6];
        yield ['twice(this.add(1))', ['this' => $object], 8];
        yield ['twice(missing ?? 4)', [], 8];
        yield ['twice(list[1])', ['list' => [1, 5]], 10];
        yield ['twice(foo?.count ?? 1)', ['foo' => null], 2];
        yield ['twice(count(list))', ['list' => [1, 5]], 4];
        yield ['twice(a) ~ values', ['a' => 1, 'values' => 'c'], '2c'];
        yield ['twice(1) ~ evaluated()', [], '2evaluated'];
        yield ['twice(arg("a")) ~ values ~ functions', ['a' => 2, 'values' => 'b', 'functions' => 'c'], '4bc'];
    }

    public function testEvaluateCompiledExpressionFailsLikeTheEvaluatedOne()
    {
        $file = $this->dumpCompiled(['not foo.locked', 'foo.lock()']);
        $expressionLanguage = self::createLanguage($file);

        try {
            $expressionLanguage->evaluate('not foo.locked', ['foo' => null]);
            $this->fail('An exception should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Unable to get property "locked" of non-object "foo".', $e->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to call method "lock" of non-object "foo".');

        $expressionLanguage->evaluate('foo.lock()', ['foo' => null]);
    }

    public function testEvaluateCompiledExpressionDoesNotCatchErrorsOfTheCode()
    {
        $file = $this->dumpCompiled(['foo.lock()']);
        $foo = new class {
            public int $calls = 0;

            public function lock(): bool
            {
                ++$this->calls;
                trigger_error('Locked twice.', \E_USER_WARNING);

                throw new \DomainException('Already locked.');
            }
        };

        $warnings = [];
        set_error_handler(static function (int $type, string $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        });

        try {
            self::createLanguage($file)->evaluate('foo.lock()', ['foo' => $foo]);
            $this->fail('An exception should have been thrown.');
        } catch (\DomainException $e) {
            $this->assertSame('Already locked.', $e->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertSame(1, $foo->calls);
        $this->assertSame(['Locked twice.'], $warnings);
    }

    public function testEvaluateCompiledExpressionWithMissingVariable()
    {
        $file = $this->dumpCompiled(['twice(a)']);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "a" is not valid around position 7 for expression `twice(a)`.');

        self::createLanguage($file)->evaluate('twice(a)', ['b' => 1]);
    }

    #[DataProvider('provideLintCases')]
    public function testLintCompiledExpression(string $expression, array $names, int $flags = 0)
    {
        $file = $this->dumpCompiled([$expression]);

        try {
            self::createLanguage()->lint($expression, $names, $flags);
            $expected = null;
        } catch (SyntaxError $e) {
            $expected = $e->getMessage();
        }

        try {
            self::createLanguage($file)->lint($expression, $names, $flags);
            $actual = null;
        } catch (SyntaxError $e) {
            $actual = $e->getMessage();
        }

        $this->assertSame($expected, $actual);
    }

    public static function provideLintCases(): iterable
    {
        yield ['a + b', ['a', 'b']];
        yield ['a + b', ['a']];
        yield ['b + a + b', []];
        yield ['a ?? 1', []];
        yield ['a ?? b', ['c']];
        yield ['a ?? 1 + a', []];
        yield ['a ?? b + a', []];
        yield ['a ?? b + a', ['b']];
        yield ['a.b ?? 1', []];
        yield ['a + b', [], Parser::IGNORE_UNKNOWN_VARIABLES];
        yield ['twice(a)', ['a'], Parser::IGNORE_UNKNOWN_FUNCTIONS];
        yield ['this.count', ['this']];
        yield ['this.count', ['thus']];
        yield ['a', ['b' => 'a']];
    }

    public function testLintDoesNotParseCompiledExpressions()
    {
        $file = $this->dumpCompiled(['twice(a)']);

        (new CompiledExpressionLanguage(new ExpressionLanguage(), $file))->lint('twice(a)', ['a']);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "twice" does not exist around position 1 for expression `twice(a)`.');

        (new ExpressionLanguage())->lint('twice(a)', ['a']);
    }

    public function testDumpCompiledSkipsExpressionsThatCannotBeCompiled()
    {
        $file = $this->dumpCompiled(['twice(', 'evaluated()', 'self()', 'twice(1)']);
        $code = file_get_contents($file);

        $this->assertStringContainsString("'evaluated()'", $code);
        $this->assertStringNotContainsString("'self()'", $code);
        $this->assertStringContainsString("'twice(1)'", $code);

        $expressionLanguage = self::createLanguage($file);
        $this->assertSame('evaluated', $expressionLanguage->evaluate('evaluated()'));
        $this->assertSame('evaluated', $expressionLanguage->evaluate('self()'));
        $this->assertSame(2, $expressionLanguage->evaluate('twice(1)'));

        $this->expectException(SyntaxError::class);
        $expressionLanguage->evaluate('twice(');
    }

    public function testCompiledExpressionsCanBeLoadedTwice()
    {
        $file = $this->dumpCompiled(['twice(2)']);

        $this->assertSame(4, self::createLanguage($file)->evaluate('twice(2)'));
        $this->assertSame(4, self::createLanguage($file)->evaluate('twice(2)'));
    }

    public function testMissingCompiledExpressionsFile()
    {
        $expressionLanguage = new CompiledExpressionLanguage(new ExpressionLanguage(), sys_get_temp_dir().'/does-not-exist/compiled.php');

        $this->assertSame(2, $expressionLanguage->evaluate('1 + 1'));
    }

    public function testEvaluateDoesNotInitializeTheDecoratedExpressionLanguage()
    {
        $file = $this->dumpCompiled(['a + 1']);
        $expressionLanguage = (new \ReflectionClass(ExpressionLanguage::class))->newLazyGhost(static fn () => throw new \LogicException('The decorated expression language should not be initialized.'));

        $this->assertSame(3, (new CompiledExpressionLanguage($expressionLanguage, $file))->evaluate('a + 1', ['a' => 2]));
    }

    public function testDelegatesToTheDecoratedExpressionLanguage()
    {
        $expressionLanguage = new ExpressionLanguage();
        $compiledExpressionLanguage = new CompiledExpressionLanguage($expressionLanguage);
        $compiledExpressionLanguage->addFunction(new ExpressionFunction('triple', static fn ($value) => \sprintf('(%s * 3)', $value), static fn (array $values, $value) => $value * 3));

        $this->assertSame(6, $expressionLanguage->evaluate('triple(2)'));
        $this->assertSame(6, $compiledExpressionLanguage->evaluate('triple(2)'));
        $this->assertSame('(2 * 3)', $compiledExpressionLanguage->compile('triple(2)'));
        $this->assertEquals($expressionLanguage->parse('triple(a)', ['a']), $compiledExpressionLanguage->parse('triple(a)', ['a']));
    }

    private function dumpCompiled(array $expressions): string
    {
        $this->compiledFiles[] = $file = tempnam(sys_get_temp_dir(), 'sf_compiled_expressions_');
        file_put_contents($file, self::createLanguage()->dumpCompiled($expressions));

        return $file;
    }

    private static function createLanguage(?string $compiledExpressionsFile = null): CompiledExpressionLanguage
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->addFunction(new ExpressionFunction('twice', static fn ($value) => \sprintf('(%s * 2)', $value), static fn () => throw new \LogicException('The expression was not compiled.')));
        $expressionLanguage->addFunction(new ExpressionFunction('evaluated', static fn () => throw new \LogicException('Cannot be compiled.'), static fn () => 'evaluated'));
        $expressionLanguage->addFunction(new ExpressionFunction('self', static fn () => '$this', static fn () => 'evaluated'));
        $expressionLanguage->addFunction(new ExpressionFunction('arg', static fn () => throw new \LogicException('Cannot be compiled.'), static fn (array $values, string $name) => $values[$name]));

        return new CompiledExpressionLanguage($expressionLanguage, $compiledExpressionsFile);
    }
}
