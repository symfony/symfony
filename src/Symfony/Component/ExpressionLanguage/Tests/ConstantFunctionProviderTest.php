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
use Symfony\Component\ExpressionLanguage\ConstantFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\FooBackedEnum;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\FooConstants;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\FooEnum;

class ConstantFunctionProviderTest extends TestCase
{
    private array $compiledFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->compiledFiles as $file) {
            @unlink($file);
        }
    }

    #[DataProvider('provideAllowedConstants')]
    public function testAllowedConstant(array $allowedConstants, string $name, mixed $expected)
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider($allowedConstants)]);

        foreach ($this->calls($expressionLanguage, 'constant', $name) as $way => $call) {
            $this->assertSame($expected, $call(), $way);
        }
    }

    public static function provideAllowedConstants(): iterable
    {
        yield 'exact class constant' => [[FooConstants::class.'::ROLE_ADMIN'], FooConstants::class.'::ROLE_ADMIN', 'admin'];
        yield 'constant name prefix' => [[FooConstants::class.'::ROLE_*'], FooConstants::class.'::ROLE_ADMIN', 'admin'];
        yield 'all constants of a class' => [[FooConstants::class.'::*'], FooConstants::class.'::SECRET', 'secret'];
        yield 'namespace prefix covers sub-namespaces' => [['Symfony\Component\ExpressionLanguage\Tests\*::*'], FooConstants::class.'::SECRET', 'secret'];
        yield 'leading backslash in the name' => [[FooConstants::class.'::ROLE_ADMIN'], '\\'.FooConstants::class.'::ROLE_ADMIN', 'admin'];
        yield 'leading backslash in the pattern' => [['\\'.FooConstants::class.'::ROLE_ADMIN'], FooConstants::class.'::ROLE_ADMIN', 'admin'];
        yield 'exact global constant' => [['PHP_VERSION'], 'PHP_VERSION', \PHP_VERSION];
        yield 'global constant prefix' => [['PHP_*'], 'PHP_EOL', \PHP_EOL];
        yield 'one of several patterns' => [['E_ALL', FooConstants::class.'::SECRET'], FooConstants::class.'::SECRET', 'secret'];
        yield 'enum case' => [[FooEnum::class.'::*'], FooEnum::class.'::Foo', FooEnum::Foo];
    }

    #[DataProvider('provideDeniedConstants')]
    public function testDeniedConstant(array $allowedConstants, string $name)
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider($allowedConstants)]);

        foreach ($this->calls($expressionLanguage, 'constant', $name) as $way => $call) {
            try {
                $call();
                $e = null;
            } catch (\RuntimeException $e) {
            }

            $this->assertSame(\sprintf('Constant "%s" is not allowed.', $name), $e?->getMessage(), $way);
        }
    }

    public static function provideDeniedConstants(): iterable
    {
        yield 'empty list' => [[], 'PHP_VERSION'];
        yield 'global constant not listed' => [['PHP_*'], 'E_ALL'];
        yield 'other constant of the class' => [[FooConstants::class.'::ROLE_*'], FooConstants::class.'::SECRET'];
        yield 'class sharing the prefix' => [[FooConstants::class.'::*'], FooConstants::class.'X::SECRET'];
        yield 'class in another case' => [[FooConstants::class.'::*'], strtolower(FooConstants::class).'::SECRET'];
        yield 'wildcard does not cross ::' => [['Symfony\Component\ExpressionLanguage\Tests\Fixtures\*'], FooConstants::class.'::SECRET'];
        yield 'namespaced constant with an allowed suffix' => [['PHP_VERSION'], 'Foo\PHP_VERSION'];
        yield 'trailing newline' => [['PHP_VERSION'], "PHP_VERSION\n"];
        yield 'two leading backslashes' => [['PHP_VERSION'], '\\\\PHP_VERSION'];
        yield 'self' => [['*::*'], 'self::SECRET'];
        yield 'static' => [['*::*'], 'static::SECRET'];
        yield 'parent' => [['*::*'], 'parent::SECRET'];
        yield 'self with a leading backslash' => [['*::*'], '\self::SECRET'];
        yield 'self in another case' => [['*::*'], 'SELF::SECRET'];
    }

    public function testAllowedEnum()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider(['Symfony\Component\ExpressionLanguage\Tests\Fixtures\Foo*Enum::*'])]);

        foreach ($this->calls($expressionLanguage, 'enum', FooBackedEnum::class.'::Bar') as $way => $call) {
            $this->assertSame(FooBackedEnum::Bar, $call(), $way);
        }
    }

    public function testDeniedEnum()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider([FooEnum::class.'::*'])]);
        $name = FooBackedEnum::class.'::Bar';

        foreach ($this->calls($expressionLanguage, 'enum', $name) as $way => $call) {
            try {
                $call();
                $e = null;
            } catch (\RuntimeException $e) {
            }

            $this->assertSame(\sprintf('Enum case "%s" is not allowed.', $name), $e?->getMessage(), $way);
        }
    }

    public function testAllowedConstantThatIsNotAnEnumCase()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider([FooConstants::class.'::*'])]);
        $name = FooConstants::class.'::SECRET';

        foreach ($this->calls($expressionLanguage, 'enum', $name) as $way => $call) {
            try {
                $call();
                $e = null;
            } catch (\TypeError $e) {
            }

            $this->assertSame(\sprintf('The string "%s" is not the name of a valid enum case.', $name), $e?->getMessage(), $way);
        }
    }

    public function testLiteralNamesAreCheckedWhenCompiling()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider(['PHP_VERSION'])]);

        $this->assertSame('\constant("PHP_VERSION")', $expressionLanguage->compile('constant("PHP_VERSION")'));
        $this->assertSame('(\constant("PHP_VERSION") instanceof \UnitEnum ? \constant("PHP_VERSION") : throw new \TypeError(\'The string "PHP_VERSION" is not the name of a valid enum case.\'))', $expressionLanguage->compile('enum("PHP_VERSION")'));
        $this->assertSame('(throw new \RuntimeException(\'Constant "E_ALL" is not allowed.\'))', $expressionLanguage->compile('constant("E_ALL")'));
        $this->assertSame('(throw new \RuntimeException(\'Enum case "E_ALL" is not allowed.\'))', $expressionLanguage->compile('enum("E_ALL")'));
        $this->assertTrue(eval('return '.$expressionLanguage->compile('true or constant("E_ALL")').';'));
    }

    public function testCompiledCodeDoesNotOverwriteVariables()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider([FooConstants::class.'::*', FooEnum::class.'::*'])]);
        $values = ['name' => FooConstants::class.'::SECRET', 'enumName' => FooEnum::class.'::Foo', 'v' => '!'];
        $expressions = ['constant(name) ~ v', 'enum(enumName).name ~ v', \sprintf('enum("%s").name ~ v', addcslashes(FooEnum::class.'::Foo', '\\'))];
        $compiledExpressionLanguage = $this->compile($expressionLanguage, $expressions);

        foreach ($expressions as $expression) {
            $expected = $expressionLanguage->evaluate($expression, $values);
            $this->assertSame($expected, eval('extract($values); return '.$expressionLanguage->compile($expression, array_keys($values)).';'), $expression);
            $this->assertSame($expected, $compiledExpressionLanguage->evaluate($expression, $values), $expression);
        }
    }

    #[DataProvider('provideClassNames')]
    public function testClassNameWithoutConstantIsRejected(string $class)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%1$s" is a class name, use "%1$s::*" to allow its constants.', $class));

        new ConstantFunctionProvider([$class]);
    }

    public static function provideClassNames(): iterable
    {
        yield [FooConstants::class];
        yield [FooEnum::class];
        yield [\Countable::class];
    }

    private function calls(ExpressionLanguage $expressionLanguage, string $function, string $name): iterable
    {
        $literal = \sprintf('%s("%s")', $function, addcslashes($name, "\\\"\n"));
        $compiledExpressionLanguage = $this->compile($expressionLanguage, [$function.'(name)', $literal]);

        yield 'evaluated variable' => static fn () => $expressionLanguage->evaluate($function.'(name)', ['name' => $name]);
        yield 'compiled variable' => static fn () => eval('$name = '.var_export($name, true).'; return '.$expressionLanguage->compile($function.'(name)', ['name']).';');
        yield 'precompiled variable' => static fn () => $compiledExpressionLanguage->evaluate($function.'(name)', ['name' => $name]);
        yield 'evaluated literal' => static fn () => $expressionLanguage->evaluate($literal);
        yield 'compiled literal' => static fn () => eval('return '.$expressionLanguage->compile($literal).';');
        yield 'precompiled literal' => static fn () => $compiledExpressionLanguage->evaluate($literal);
    }

    private function compile(ExpressionLanguage $expressionLanguage, array $expressions): CompiledExpressionLanguage
    {
        $this->compiledFiles[] = $file = tempnam(sys_get_temp_dir(), 'sf_compiled_expressions_');
        file_put_contents($file, (new CompiledExpressionLanguage($expressionLanguage))->dumpCompiled($expressions));

        return new CompiledExpressionLanguage($expressionLanguage, $file);
    }
}
