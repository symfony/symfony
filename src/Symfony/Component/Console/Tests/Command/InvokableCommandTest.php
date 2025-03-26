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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class InvokableCommandTest extends TestCase
{
    public function testCommandInputArgumentDefinition()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument(name: 'first-name')] string $name,
            #[Argument] ?string $firstName,
            #[Argument] string $lastName = '',
            #[Argument(description: 'Short argument description')] string $bio = '',
            #[Argument(suggestedValues: [self::class, 'getSuggestedRoles'])] array $roles = ['ROLE_USER'],
        ) {});

        $nameInputArgument = $command->getDefinition()->getArgument('first-name');
        self::assertSame('first-name', $nameInputArgument->getName());
        self::assertTrue($nameInputArgument->isRequired());

        $lastNameInputArgument = $command->getDefinition()->getArgument('firstName');
        self::assertSame('firstName', $lastNameInputArgument->getName());
        self::assertFalse($lastNameInputArgument->isRequired());
        self::assertNull($lastNameInputArgument->getDefault());

        $lastNameInputArgument = $command->getDefinition()->getArgument('lastName');
        self::assertSame('lastName', $lastNameInputArgument->getName());
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
        ) {});

        $timeoutInputOption = $command->getDefinition()->getOption('idle');
        self::assertSame('idle', $timeoutInputOption->getName());
        self::assertNull($timeoutInputOption->getShortcut());
        self::assertTrue($timeoutInputOption->isValueOptional());
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
    }

    public function testInvalidArgumentType()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Argument] object $any) {});

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The type "object" of parameter "$any" is not supported as a command argument. Only "string", "bool", "int", "float", "array" types are allowed.');

        $command->getDefinition();
    }

    public function testInvalidOptionType()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Option] object $any) {});

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The type "object" of parameter "$any" is not supported as a command option. Only "string", "bool", "int", "float", "array" types are allowed.');

        $command->getDefinition();
    }

    /**
     * @dataProvider provideInputArguments
     */
    public function testInputArguments(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument] string $a,
            #[Argument] ?string $b,
            #[Argument] string $c = '',
            #[Argument] array $d = [],
        ) use ($expected) {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);
            $this->assertSame($expected[3], $d);
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideInputArguments(): \Generator
    {
        yield 'required & defaults' => [['a' => 'x'], ['x', null, '', []]];
        yield 'required & with-value' => [['a' => 'x', 'b' => 'y', 'c' => 'z', 'd' => ['d']], ['x', 'y', 'z', ['d']]];
        yield 'required & without-value' => [['a' => 'x', 'b' => null, 'c' => null, 'd' => null], ['x', null, '', []]];
    }

    /**
     * @dataProvider provideBinaryInputOptions
     */
    public function testBinaryInputOptions(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option] bool $a = true,
            #[Option] bool $b = false,
            #[Option] ?bool $c = null,
        ) use ($expected) {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideBinaryInputOptions(): \Generator
    {
        yield 'defaults' => [[], [true, false, null]];
        yield 'positive' => [['--a' => null, '--b' => null, '--c' => null], [true, true, true]];
        yield 'negative' => [['--no-a' => null, '--no-c' => null], [false, false, false]];
    }

    /**
     * @dataProvider provideNonBinaryInputOptions
     */
    public function testNonBinaryInputOptions(array $parameters, array $expected)
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Option] ?string $a = null,
            #[Option] ?string $b = 'b',
            #[Option] ?array $c = [],
        ) use ($expected) {
            $this->assertSame($expected[0], $a);
            $this->assertSame($expected[1], $b);
            $this->assertSame($expected[2], $c);
        });

        $command->run(new ArrayInput($parameters), new NullOutput());
    }

    public static function provideNonBinaryInputOptions(): \Generator
    {
        yield 'defaults' => [[], [null, 'b', []]];
        yield 'with-value' => [['--a' => 'x', '--b' => 'y', '--c' => ['z']], ['x', 'y', ['z']]];
        yield 'without-value' => [['--a' => null, '--b' => null, '--c' => null], [null, null, null]];
    }

    public function testInvalidOptionDefinition()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Option] string $a) {});

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The option parameter "$a" must declare a default value.');

        $command->getDefinition();
    }

    public function testInvalidRequiredValueOptionEvenWithDefault()
    {
        $command = new Command('foo');
        $command->setCode(function (#[Option] string $a = 'a') {});

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "--a" option requires a value.');

        $command->run(new ArrayInput(['--a' => null]), new NullOutput());
    }

    public function getSuggestedRoles(CompletionInput $input): array
    {
        return ['ROLE_ADMIN', 'ROLE_USER'];
    }
}
