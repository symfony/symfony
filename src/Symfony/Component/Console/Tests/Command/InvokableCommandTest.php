<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tests\Fixtures\InvokableTestCommand;

class InvokableCommandTest extends TestCase
{
    public function testCommandInputArgumentDefinition()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument(name: 'very-first-name')] string $name,
            #[Argument] ?string $firstName,
            #[Argument] string $lastName = '',
            #[Argument(description: 'Short argument description')] string $bio = '',
            #[Argument(suggestedValues: [self::class, 'getSuggestedRoles'])] array $roles = ['ROLE_USER'],
        ): int {
            return 0;
        });

        $nameInputArgument = $command->getDefinition()->getArgument('very-first-name');
        self::assertSame('very-first-name', $nameInputArgument->getName());
        self::assertTrue($nameInputArgument->isRequired());

        $lastNameInputArgument = $command->getDefinition()->getArgument('first-name');
        self::assertSame('first-name', $lastNameInputArgument->getName());
        self::assertFalse($lastNameInputArgument->isRequired());
        self::assertNull($lastNameInputArgument->getDefault());

        $lastNameInputArgument = $command->getDefinition()->getArgument('last-name');
        self::assertSame('last-name', $lastNameInputArgument->getName());
        self::assertFalse($lastNameInputArgument->isRequired());
        self::assertSame('', $lastNameInputArgument->getDefault());

        $bioInputArgument = $command->getDefinition()->getArgument('bio');
        self::assertSame('bio', $bioInputArgument->getName());
        self::assertFalse($bioInputArgument->isRequired());
        self::assertSame('Short argument description', $bioInputArgument->getDescription());
        self::assertSame('', $bioInputArgument->getDefault());

        $rolesInputArgument = $command->getDefinition()->getArgument('roles');
        self::assertSame('roles', $rolesInputArgument->getName());
        self::assertFalse($rolesInputArgument->isRequired());
        self::assertTrue($rolesInputArgument->isArray());
        self::assertSame(['ROLE_USER'], $rolesInputArgument->getDefault());
        self::assertTrue($rolesInputArgument->hasCompletion());
        $rolesInputArgument->complete(new CompletionInput(), $suggestions = new CompletionSuggestions());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_map(static fn (Suggestion $s) => $s->getValue(), $suggestions->getValueSuggestions()));
    }

    public function testCommandInputOptionDefinition()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option(name: 'idle')] ?int $timeout = null,
            #[Option] string $type = 'USER_TYPE',
            #[Option(shortcut: 'v')] bool $verbose = false,
            #[Option(description: 'User groups')] array $groups = [],
            #[Option(suggestedValues: [self::class, 'getSuggestedRoles'])] array $roles = ['ROLE_USER'],
            #[Option] string|bool $opt = false,
        ): int {
            return 0;
        });

        $timeoutInputOption = $command->getDefinition()->getOption('idle');
        self::assertSame('idle', $timeoutInputOption->getName());
        self::assertNull($timeoutInputOption->getShortcut());
        self::assertTrue($timeoutInputOption->isValueRequired());
        self::assertFalse($timeoutInputOption->isValueOptional());
        self::assertFalse($timeoutInputOption->isNegatable());
        self::assertNull($timeoutInputOption->getDefault());

        $typeInputOption = $command->getDefinition()->getOption('type');
        self::assertSame('type', $typeInputOption->getName());
        self::assertTrue($typeInputOption->isValueRequired());
        self::assertFalse($typeInputOption->isNegatable());
        self::assertSame('USER_TYPE', $typeInputOption->getDefault());

        $verboseInputOption = $command->getDefinition()->getOption('verbose');
        self::assertSame('verbose', $verboseInputOption->getName());
        self::assertSame('v', $verboseInputOption->getShortcut());
        self::assertFalse($verboseInputOption->isValueRequired());
        self::assertFalse($verboseInputOption->isValueOptional());
        self::assertFalse($verboseInputOption->isNegatable());
        self::assertFalse($verboseInputOption->getDefault());

        $groupsInputOption = $command->getDefinition()->getOption('groups');
        self::assertSame('groups', $groupsInputOption->getName());
        self::assertTrue($groupsInputOption->isArray());
        self::assertSame('User groups', $groupsInputOption->getDescription());
        self::assertFalse($groupsInputOption->isNegatable());
        self::assertSame([], $groupsInputOption->getDefault());

        $rolesInputOption = $command->getDefinition()->getOption('roles');
        self::assertSame('roles', $rolesInputOption->getName());
        self::assertTrue($rolesInputOption->isValueRequired());
        self::assertFalse($rolesInputOption->isNegatable());
        self::assertTrue($rolesInputOption->isArray());
        self::assertSame(['ROLE_USER'], $rolesInputOption->getDefault());
        self::assertTrue($rolesInputOption->hasCompletion());
        $rolesInputOption->complete(new CompletionInput(), $suggestions = new CompletionSuggestions());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_map(static fn (Suggestion $s) => $s->getValue(), $suggestions->getValueSuggestions()));

        $optInputOption = $command->getDefinition()->getOption('opt');
        self::assertSame('opt', $optInputOption->getName());
        self::assertNull($optInputOption->getShortcut());
        self::assertFalse($optInputOption->isValueRequired());
        self::assertTrue($optInputOption->isValueOptional());
        self::assertFalse($optInputOption->isNegatable());
        self::assertFalse($optInputOption->getDefault());
    }

    public function testEnumArgument()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument] StringEnum $enum,
            #[Argument] StringEnum $enumWithDefault = StringEnum::Image,
            #[Argument] ?StringEnum $nullableEnum = null,
        ): int {
            Assert::assertSame(StringEnum::Image, $enum);
            Assert::assertSame(StringEnum::Image, $enumWithDefault);
            Assert::assertNull($nullableEnum);

            return 0;
        });

        $enumInputArgument = $command->getDefinition()->getArgument('enum');
        self::assertTrue($enumInputArgument->isRequired());
        self::assertNull($enumInputArgument->getDefault());
        self::assertTrue($enumInputArgument->hasCompletion());

        $enumWithDefaultInputArgument = $command->getDefinition()->getArgument('enum-with-default');
        self::assertFalse($enumWithDefaultInputArgument->isRequired());
        self::assertSame('image', $enumWithDefaultInputArgument->getDefault());
        self::assertTrue($enumWithDefaultInputArgument->hasCompletion());

        $nullableEnumInputArgument = $command->getDefinition()->getArgument('nullable-enum');
        self::assertFalse($nullableEnumInputArgument->isRequired());
        self::assertNull($nullableEnumInputArgument->getDefault());
        self::assertTrue($nullableEnumInputArgument->hasCompletion());

        $enumInputArgument->complete(CompletionInput::fromTokens([], 0), $suggestions = new CompletionSuggestions());
        self::assertEquals([new Suggestion('image'), new Suggestion('video')], $suggestions->getValueSuggestions());

        $command->run(new ArrayInput(['enum' => 'image']), new NullOutput());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The value "incorrect" is not valid for the "enum" argument. Supported values are "image", "video".');

        $command->run(new ArrayInput(['enum' => 'incorrect']), new NullOutput());
    }

    public function testEnumOption()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option] StringEnum $enum = StringEnum::Video,
            #[Option] StringEnum $enumWithDefault = StringEnum::Image,
            #[Option] ?StringEnum $nullableEnum = null,
        ): int {
            Assert::assertSame(StringEnum::Image, $enum);
            Assert::assertSame(StringEnum::Image, $enumWithDefault);
            Assert::assertNull($nullableEnum);

            return 0;
        });

        $enumInputOption = $command->getDefinition()->getOption('enum');
        self::assertTrue($enumInputOption->isValueRequired());
        self::assertSame('video', $enumInputOption->getDefault());
        self::assertTrue($enumInputOption->hasCompletion());

        $enumWithDefaultInputOption = $command->getDefinition()->getOption('enum-with-default');
        self::assertTrue($enumWithDefaultInputOption->isValueRequired());
        self::assertSame('image', $enumWithDefaultInputOption->getDefault());
        self::assertTrue($enumWithDefaultInputOption->hasCompletion());

        $nullableEnumInputOption = $command->getDefinition()->getOption('nullable-enum');
        self::assertTrue($nullableEnumInputOption->isValueRequired());
        self::assertNull($nullableEnumInputOption->getDefault());
        self::assertTrue($nullableEnumInputOption->hasCompletion());

        $enumInputOption->complete(CompletionInput::fromTokens([], 0), $suggestions = new CompletionSuggestions());
        self::assertEquals([new Suggestion('image'), new Suggestion('video')], $suggestions->getValueSuggestions());

        $command->run(new ArrayInput(['--enum' => 'image']), new NullOutput());

        self::expectException(InvalidOptionException::class);
        self::expectExceptionMessage('The value "incorrect" is not valid for the "enum" option. Supported values are "image", "video".');

        $command->run(new ArrayInput(['--enum' => 'incorrect']), new NullOutput());
    }

    public function testInvalidArgumentType()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Argument] object $any) {});

        $this->expectException(LogicException::class);

        $command->getDefinition();
    }

    public function testInvalidOptionType()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Option] ?object $any = null) {});

        $this->expectException(LogicException::class);

        $command->getDefinition();
    }

    public function testExecuteHasPriorityOverInvokeMethod()
    {
        $command = new class extends Command {
            public string $called;

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->called = __FUNCTION__;

                return 0;
            }

            public function __invoke(): int
            {
                $this->called = __FUNCTION__;

                return 0;
            }
        };

        $command->run(new ArrayInput([]), new NullOutput());
        $this->assertSame('execute', $command->called);
    }

    public function testCallInvokeMethodWhenExtendingCommandClass()
    {
        $command = new class extends Command {
            public string $called;

            public function __invoke(): int
            {
                $this->called = __FUNCTION__;

                return 0;
            }
        };

        $command->run(new ArrayInput([]), new NullOutput());
        $this->assertSame('__invoke', $command->called);
    }

    public function testInvalidReturnType()
    {
        $command = new Command('foo');
        $command->setCode(new class {
            public function __invoke()
            {
            }
        });

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The command "foo" must return an integer value in the "__invoke" method, but "null" was returned.');

        $command->run(new ArrayInput([]), new NullOutput());
    }

    public function testGetCode()
    {
        $invokableTestCommand = new InvokableTestCommand();
        $command = new Command(null, $invokableTestCommand);

        $this->assertSame($invokableTestCommand, $command->getCode());
    }

    #[DataProvider('provideInputArguments')]
    public function testInputArguments(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument] string $a,
            #[Argument] ?string $b,
            #[Argument] string $c = '',
            #[Argument] array $d = [],
        ) use ($expected): int {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);
            $this->assertSame($expected[3], $d);

            return 0;
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideInputArguments(): \Generator
    {
        yield 'required & defaults' => [['a' => 'x'], ['x', null, '', []]];
        yield 'required & with-value' => [['a' => 'x', 'b' => 'y', 'c' => 'z', 'd' => ['d']], ['x', 'y', 'z', ['d']]];
        yield 'required & without-value' => [['a' => 'x', 'b' => null, 'c' => null, 'd' => null], ['x', null, '', []]];
    }

    #[DataProvider('provideBinaryInputOptions')]
    public function testBinaryInputOptions(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option] bool $a = true,
            #[Option] bool $b = false,
            #[Option] ?bool $c = null,
        ) use ($expected): int {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);

            return 0;
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideBinaryInputOptions(): \Generator
    {
        yield 'defaults' => [[], [true, false, null]];
        yield 'positive' => [['--a' => null, '--b' => null, '--c' => null], [true, true, true]];
        yield 'negative' => [['--no-a' => null, '--no-c' => null], [false, false, false]];
    }

    #[DataProvider('provideNonBinaryInputOptions')]
    public function testNonBinaryInputOptions(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option] string $a = '',
            #[Option] array $b = [],
            #[Option] array $c = ['a', 'b'],
            #[Option] bool|string $d = false,
            #[Option] ?string $e = null,
            #[Option] ?array $f = null,
            #[Option] int $g = 0,
            #[Option] ?int $h = null,
            #[Option] float $i = 0.0,
            #[Option] ?float $j = null,
            #[Option] bool|int $k = false,
            #[Option] bool|float $l = false,
        ) use ($expected): int {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);
            $this->assertSame($expected[3], $d);
            $this->assertSame($expected[4], $e);
            $this->assertSame($expected[5], $f);
            $this->assertSame($expected[6], $g);
            $this->assertSame($expected[7], $h);
            $this->assertSame($expected[8], $i);
            $this->assertSame($expected[9], $j);
            $this->assertSame($expected[10], $k);
            $this->assertSame($expected[11], $l);

            return 0;
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideNonBinaryInputOptions(): \Generator
    {
        yield 'defaults' => [
            [],
            ['', [], ['a', 'b'], false, null, null, 0, null, 0.0, null, false, false],
        ];
        yield 'with-value' => [
            ['--a' => 'x', '--b' => ['z'], '--c' => ['c', 'd'], '--d' => 'v', '--e' => 'w', '--f' => ['q'], '--g' => 1, '--h' => 2, '--i' => 3.1, '--j' => 4.2, '--k' => 5, '--l' => 6.3],
            ['x', ['z'], ['c', 'd'], 'v', 'w', ['q'], 1, 2, 3.1, 4.2, 5, 6.3],
        ];
        yield 'without-value' => [
            ['--d' => null, '--k' => null, '--l' => null],
            ['', [], ['a', 'b'], true, null, null, 0, null, 0.0, null, true, true],
        ];
    }

    #[DataProvider('provideInvalidOptionDefinitions')]
    public function testInvalidOptionDefinition(callable $code)
    {
        $command = new Command('foo');
        $command->setCode($code);

        $this->expectException(LogicException::class);

        $command->getDefinition();
    }

    public static function provideInvalidOptionDefinitions(): \Generator
    {
        yield 'no-default' => [
            function (#[Option] string $a) {},
        ];
        yield 'nullable-bool-default-true' => [
            function (#[Option] ?bool $a = true) {},
        ];
        yield 'nullable-bool-default-false' => [
            function (#[Option] ?bool $a = false) {},
        ];
        yield 'invalid-union-type' => [
            function (#[Option] array|bool $a = false) {},
        ];
        yield 'union-type-cannot-allow-null' => [
            function (#[Option] string|bool|null $a = null) {},
        ];
        yield 'union-type-default-true' => [
            function (#[Option] string|bool $a = true) {},
        ];
        yield 'union-type-default-string' => [
            function (#[Option] string|bool $a = 'foo') {},
        ];
        yield 'nullable-string-not-null-default' => [
            function (#[Option] ?string $a = 'foo') {},
        ];
        yield 'nullable-array-not-null-default' => [
            function (#[Option] ?array $a = []) {},
        ];
    }

    public function testInvalidRequiredValueOptionEvenWithDefault()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Option] string $a = 'a') {});

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "--a" option requires a value.');

        $command->run(new ArrayInput(['--a' => null]), new NullOutput());
    }

    public function testHelpersInjection()
    {
        $command = new Command('foo');
        $command->setApplication(new Application());
        $command->setCode(function (
            InputInterface $input,
            OutputInterface $output,
            Cursor $cursor,
            SymfonyStyle $io,
            Application $application,
        ): int {
            $this->addToAssertionCount(1);

            return 0;
        });

        $command->run(new ArrayInput([]), new NullOutput());
    }

    public function getSuggestedRoles(CompletionInput $input): array
    {
        return ['ROLE_ADMIN', 'ROLE_USER'];
    }
}

enum StringEnum: string
{
    case Image = 'image';
    case Video = 'video';
}
