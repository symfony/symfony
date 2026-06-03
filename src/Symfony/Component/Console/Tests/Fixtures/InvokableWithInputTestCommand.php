<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('invokable:input:test')]
class InvokableWithInputTestCommand
{
    #[Interact]
    public function interact(SymfonyStyle $io, #[MapInput] UserDto $user): void
    {
        $user->email ??= 'user.interactive@command.com';
    }

    public function __invoke(SymfonyStyle $io, #[MapInput] UserDto $user): int
    {
        $io->writeln($user->name);
        $io->writeln($user->email);
        $io->writeln($user->password);
        $io->writeln($user->admin ? 'yes' : 'no');
        $io->writeln($user->active ? 'yes' : 'no');
        $io->writeln($user->status->value);
        $io->writeln($user->group->name);
        $io->writeln($user->group->description);

        return Command::SUCCESS;
    }
}

final class UserDto
{
    #[Argument(name: 'username')]
    public string $name;

    #[Argument]
    public string $email;

    #[Argument]
    public string $password;

    #[MapInput]
    public UserGroupDto $group;

    #[Option]
    public bool $admin = false;

    #[Option]
    public bool $active = true;

    #[Option]
    public UserStatus $status = UserStatus::Unverified;

    #[Interact]
    public function interact(SymfonyStyle $io): void
    {
        $this->password ??= 'user-dto-interactive-password';
    }
}

final class UserGroupDto
{
    #[Option(name: 'group')]
    public string $name = 'users';

    #[Option(name: 'group-description')]
    public string $description = 'Standard Users';
}

enum UserStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Locked = 'locked';
}
