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
use Symfony\Component\Console\Exception\LogicException;

class InvokableCommandTest extends TestCase
{
    public function testCommandInputArgumentDefinition()
    {
        $command = new Command('foo');
        $command->setCode(function (
            #[Argument(name: 'first-name')] string $name,
            #[Argument(default: '')] string $lastName,
            #[Argument(description: 'Short argument description')] string $bio = '',
            #[Argument(suggestedValues: [self::class, 'getSuggestedRoles'])] array $roles = ['ROLE_USER'],
        ) {});

        $nameInputArgument = $command->getDefinition()->getArgument('first-name');
        self::assertSame('first-name', $nameInputArgument->getName());
        self::assertTrue($nameInputArgument->isRequired());

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
            #[Option(name: 'idle')] int $timeout,
            #[Option(default: 'USER_TYPE')] string $type,
            #[Option(shortcut: 'v')] bool $verbose = false,
            #[Option(description: 'User groups')] array $groups = [],
            #[Option(suggestedValues: [self::class, 'getSuggestedRoles'])] array $roles = ['ROLE_USER'],
        ) {});

        $timeoutInputOption = $command->getDefinition()->getOption('idle');
        self::assertSame('idle', $timeoutInputOption->getName());
        self::assertNull($timeoutInputOption->getShortcut());
        self::assertTrue($timeoutInputOption->isValueRequired());
        self::assertNull($timeoutInputOption->getDefault());

        $typeInputOption = $command->getDefinition()->getOption('type');
        self::assertSame('type', $typeInputOption->getName());
        self::assertFalse($typeInputOption->isValueRequired());
        self::assertSame('USER_TYPE', $typeInputOption->getDefault());

        $verboseInputOption = $command->getDefinition()->getOption('verbose');
        self::assertSame('verbose', $verboseInputOption->getName());
        self::assertSame('v', $verboseInputOption->getShortcut());
        self::assertFalse($verboseInputOption->isValueRequired());
        self::assertTrue($verboseInputOption->isNegatable());
        self::assertNull($verboseInputOption->getDefault());

        $groupsInputOption = $command->getDefinition()->getOption('groups');
        self::assertSame('groups', $groupsInputOption->getName());
        self::assertTrue($groupsInputOption->isArray());
        self::assertSame('User groups', $groupsInputOption->getDescription());
        self::assertSame([], $groupsInputOption->getDefault());

        $rolesInputOption = $command->getDefinition()->getOption('roles');
        self::assertSame('roles', $rolesInputOption->getName());
        self::assertFalse($rolesInputOption->isValueRequired());
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

    public function getSuggestedRoles(CompletionInput $input): array
    {
        return ['ROLE_ADMIN', 'ROLE_USER'];
    }
}
