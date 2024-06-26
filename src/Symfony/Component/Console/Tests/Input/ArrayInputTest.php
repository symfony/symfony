<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Input;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class ArrayInputTest extends TestCase
{
    public function testGetFirstArgument()
    {
        $input = new ArrayInput([]);
        $this->assertNull($input->getFirstArgument(), '->getFirstArgument() returns null if no argument were passed');
        $input = new ArrayInput(['name' => 'Fabien']);
        $this->assertEquals('Fabien', $input->getFirstArgument(), '->getFirstArgument() returns the first passed argument');
        $input = new ArrayInput(['--foo' => 'bar', 'name' => 'Fabien']);
        $this->assertEquals('Fabien', $input->getFirstArgument(), '->getFirstArgument() returns the first passed argument');
    }

    public function testHasParameterOption()
    {
        $input = new ArrayInput(['name' => 'Fabien', '--foo' => 'bar']);
        $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
        $this->assertFalse($input->hasParameterOption('--bar'), '->hasParameterOption() returns false if an option is not present in the passed parameters');

        $input = new ArrayInput(['--foo']);
        $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');

        $input = new ArrayInput(['--foo', '--', '--bar']);
        $this->assertTrue($input->hasParameterOption('--bar'), '->hasParameterOption() returns true if an option is present in the passed parameters');
        $this->assertFalse($input->hasParameterOption('--bar', true), '->hasParameterOption() returns false if an option is present in the passed parameters after an end of options signal');
    }

    public function testGetParameterOption()
    {
        $input = new ArrayInput(['name' => 'Fabien', '--foo' => 'bar']);
        $this->assertEquals('bar', $input->getParameterOption('--foo'), '->getParameterOption() returns the option of specified name');
        $this->assertEquals('default', $input->getParameterOption('--bar', 'default'), '->getParameterOption() returns the default value if an option is not present in the passed parameters');

        $input = new ArrayInput(['Fabien', '--foo' => 'bar']);
        $this->assertEquals('bar', $input->getParameterOption('--foo'), '->getParameterOption() returns the option of specified name');

        $input = new ArrayInput(['--foo', '--', '--bar' => 'woop']);
        $this->assertEquals('woop', $input->getParameterOption('--bar'), '->getParameterOption() returns the correct value if an option is present in the passed parameters');
        $this->assertEquals('default', $input->getParameterOption('--bar', 'default', true), '->getParameterOption() returns the default value if an option is present in the passed parameters after an end of options signal');
    }

    public function testParseArguments()
    {
        $input = new ArrayInput(['name' => 'foo'], new InputDefinition([new InputArgument('name')]));

        $this->assertEquals(['name' => 'foo'], $input->getArguments(), '->parse() parses required arguments');
    }

    /**
     * @dataProvider provideOptions
     */
    public function testParseOptions($input, $options, $expectedOptions, $message)
    {
        $input = new ArrayInput($input, new InputDefinition($options));

        $this->assertEquals($expectedOptions, $input->getOptions(), $message);
    }

    public static function provideOptions(): array
    {
        return [
            [
                ['--foo' => 'bar'],
                [new InputOption('foo')],
                ['foo' => 'bar'],
                '->parse() parses long options',
            ],
            [
                ['--foo' => 'bar'],
                [new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default')],
                ['foo' => 'bar'],
                '->parse() parses long options with a default value',
            ],
            [
                [],
                [new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default')],
                ['foo' => 'default'],
                '->parse() uses the default value for long options with value optional which are not passed',
            ],
            [
                ['--foo' => null],
                [new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default')],
                ['foo' => null],
                '->parse() parses long options with a default value',
            ],
            [
                ['-f' => 'bar'],
                [new InputOption('foo', 'f')],
                ['foo' => 'bar'],
                '->parse() parses short options',
            ],
            [
                ['--' => null, '-f' => 'bar'],
                [new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default')],
                ['foo' => 'default'],
                '->parse() does not parse opts after an end of options signal',
            ],
            [
                ['--' => null],
                [],
                [],
                '->parse() does not choke on end of options signal',
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidInput
     */
    public function testParseInvalidInput($parameters, $definition, $expectedExceptionMessage)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new ArrayInput($parameters, $definition);
    }

    public static function provideInvalidInput(): array
    {
        return [
            [
                ['foo' => 'foo'],
                new InputDefinition([new InputArgument('name')]),
                'The "foo" argument does not exist.',
            ],
            [
                ['--foo' => null],
                new InputDefinition([new InputOption('foo', 'f', InputOption::VALUE_REQUIRED)]),
                'The "--foo" option requires a value.',
            ],
            [
                ['--foo' => 'foo'],
                new InputDefinition(),
                'The "--foo" option does not exist.',
            ],
            [
                ['-o' => 'foo'],
                new InputDefinition(),
                'The "-o" option does not exist.',
            ],
        ];
    }

    public function testToString()
    {
        $input = new ArrayInput(['-f' => null, '-b' => 'bar', '--foo' => 'b a z', '--lala' => null, 'test' => 'Foo', 'test2' => "A\nB'C"]);
        $this->assertEquals('-f -b bar --foo='.escapeshellarg('b a z').' --lala Foo '.escapeshellarg("A\nB'C"), (string) $input);

        $input = new ArrayInput(['-b' => ['bval_1', 'bval_2'], '--f' => ['fval_1', 'fval_2']]);
        $this->assertSame('-b bval_1 -b bval_2 --f=fval_1 --f=fval_2', (string) $input);

        $input = new ArrayInput(['array_arg' => ['val_1', 'val_2']]);
        $this->assertSame('val_1 val_2', (string) $input);
    }

    /**
     * @dataProvider unparseProvider
     */
    public function testUnparse(
        ?InputDefinition $inputDefinition,
        ArrayInput $input,
        ?array $parsedOptions,
        array $expected,
    ): void
    {
        if (null !== $inputDefinition) {
            $input->bind($inputDefinition);
        }

        $actual = null === $parsedOptions ? $input->unparse() : $input->unparse($parsedOptions);

        self::assertSame($expected, $actual);
    }

    public static function unparseProvider(): iterable
    {
        yield 'empty input and empty definition' => [
            new InputDefinition(),
            new ArrayInput([]),
            [],
            [],
        ];

        yield 'empty input and definition with default values: ignore default values' => [
            new InputDefinition([
                new InputArgument(
                    'argWithDefaultValue',
                    InputArgument::OPTIONAL,
                    'Argument with a default value',
                    'arg1DefaultValue',
                ),
                new InputOption(
                    'optWithDefaultValue',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Option with a default value',
                    'opt1DefaultValue',
                ),
            ]),
            new ArrayInput([]),
            [],
            [],
        ];

        $completeInputDefinition = new InputDefinition([
            new InputArgument(
                'requiredArgWithoutDefaultValue',
                InputArgument::REQUIRED,
                'Argument without a default value',
            ),
            new InputArgument(
                'optionalArgWithDefaultValue',
                InputArgument::OPTIONAL,
                'Argument with a default value',
                'argDefaultValue',
            ),
            new InputOption(
                'optWithoutDefaultValue',
                null,
                InputOption::VALUE_REQUIRED,
                'Option without a default value',
            ),
            new InputOption(
                'optWithDefaultValue',
                null,
                InputOption::VALUE_REQUIRED,
                'Option with a default value',
                'optDefaultValue',
            ),
        ]);

        yield 'arguments & options: returns all passed options but ignore default values' => [
            $completeInputDefinition,
            new ArrayInput([
                'requiredArgWithoutDefaultValue' => 'argValue',
                '--optWithoutDefaultValue' => 'optValue',
            ]),
            [],
            ['--optWithoutDefaultValue=optValue'],
        ];

        yield 'arguments & options; explicitly pass the default values: the default values are returned' => [
            $completeInputDefinition,
            new ArrayInput([
                'requiredArgWithoutDefaultValue' => 'argValue',
                'optionalArgWithDefaultValue' => 'argDefaultValue',
                '--optWithoutDefaultValue' => 'optValue',
                '--optWithDefaultValue' => 'optDefaultValue',
            ]),
            [],
            [
                '--optWithoutDefaultValue=optValue',
                '--optWithDefaultValue=optDefaultValue',
            ],
        ];

        yield 'arguments & options; no input definition: nothing returned' => [
            null,
            new ArrayInput([
                'requiredArgWithoutDefaultValue' => 'argValue',
                'optionalArgWithDefaultValue' => 'argDefaultValue',
                '--optWithoutDefaultValue' => 'optValue',
                '--optWithDefaultValue' => 'optDefaultValue',
            ]),
            [],
            [],
        ];

        yield 'arguments & options; parsing an argument name instead of an option name: that option is ignored' => [
            $completeInputDefinition,
            new ArrayInput(['requiredArgWithoutDefaultValue' => 'argValue']),
            ['requiredArgWithoutDefaultValue'],
            [],
        ];

        yield 'arguments & options; non passed option: it is ignored' => [
            $completeInputDefinition,
            new ArrayInput(['requiredArgWithoutDefaultValue' => 'argValue']),
            ['optWithDefaultValue'],
            [],
        ];

        $createSingleOptionScenario = static fn (
            InputOption $option,
            array $input,
            array $expected
        ) => [
            new InputDefinition([$option]),
            new ArrayInput($input),
            [],
            $expected,
        ];

        yield 'option without value' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NONE,
            ),
            ['--opt' => null],
            ['--opt'],
        );

        yield 'option without value by shortcut' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                'o',
                InputOption::VALUE_NONE,
            ),
            ['-o' => null],
            ['--opt'],
        );

        yield 'option with value required' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 'foo'],
            ['--opt=foo'],
        );

        yield 'option with non string value (bool)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => true],
            ['--opt=1'],
        );

        yield 'option with non string value (int)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 20],
            ['--opt=20'],
        );

        yield 'option with non string value (float)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 5.3],
            ['--opt=\'5.3\''],
        );

        yield 'option with non string value (array of strings)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            ),
            ['--opt' => ['v1', 'v2', 'v3']],
            ['--opt=v1--opt=v2--opt=v3'],
        );

        yield 'negatable option (positive)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NEGATABLE,
            ),
            ['--opt' => null],
            ['--opt'],
        );

        yield 'negatable option (negative)' => $createSingleOptionScenario(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NEGATABLE,
            ),
            ['--no-opt' => null],
            ['--no-opt'],
        );

        $createEscapeOptionTokenScenario = static fn (
            string $optionValue,
            ?string $expected
        ) => [
            new InputDefinition([
                new InputOption(
                    'opt',
                    null,
                    InputOption::VALUE_REQUIRED,
                ),
            ]),
            new ArrayInput([
                '--opt' => $optionValue,
            ]),
            [],
            [
                '--opt='.$expected,
            ],
        ];

        yield 'escape token; string token' => $createEscapeOptionTokenScenario(
            'foo',
            'foo',
        );

        yield 'escape token; escaped string token' => $createEscapeOptionTokenScenario(
            '"foo"',
            escapeshellarg('"foo"'),
        );

        yield 'escape token; escaped string token with both types of quotes' => $createEscapeOptionTokenScenario(
            '"o_id in(\'20\')"',
            escapeshellarg('"o_id in(\'20\')"'),
        );

        yield 'escape token; string token with spaces' => $createEscapeOptionTokenScenario(
            'a b c d',
            escapeshellarg('a b c d'),
        );

        yield 'escape token; string token with line return' => $createEscapeOptionTokenScenario(
            "A\nB'C",
            escapeshellarg("A\nB'C"),
        );
    }
}
