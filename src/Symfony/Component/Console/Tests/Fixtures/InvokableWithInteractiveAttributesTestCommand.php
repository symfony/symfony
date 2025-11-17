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
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('invokable:interactive:question')]
class InvokableWithInteractiveAttributesTestCommand
{
    #[Interact]
    public function prompt(SymfonyStyle $io, #[MapInput] DummyDto $dto): void
    {
        $dto->arg5 ??= $io->ask('Enter arg5');
    }

    public function __invoke(
        SymfonyStyle $io,

        #[Argument, Ask('Enter arg1')]
        string $arg1,

        #[MapInput]
        DummyDto $dto,
    ): int {
        $io->writeln('Arg1: '.$arg1);
        $io->writeln('Arg2: '.$dto->arg2->value);
        $io->writeln('Arg3: '.$dto->arg3);
        $io->writeln('Arg4: '.$dto->arg4);
        $io->writeln('Arg5: '.$dto->arg5);
        $io->writeln('Arg6: '.$dto->dummyDto2->arg6);
        $io->writeln('Arg7: '.$dto->dummyDto2->arg7);
        $io->writeln('Arg8: '.($dto->dummyDto2->arg8 ? 'yes' : 'no'));
        $io->writeln('Arg9: '.implode(',', $dto->dummyDto2->arg9));

        return Command::SUCCESS;
    }
}

class DummyDto
{
    #[Argument]
    #[Ask('Enter arg2')]
    public Arg2 $arg2;

    #[Argument]
    #[Ask('Enter arg3')]
    public string $arg3;

    #[Argument]
    public string $arg4;

    #[Argument]
    public string $arg5;

    #[MapInput]
    public DummyDto2 $dummyDto2;

    #[Interact]
    public function prompt(SymfonyStyle $io): void
    {
        $this->arg4 ??= $io->ask('Enter arg4');
    }
}

class DummyDto2
{
    #[Argument]
    #[Ask('Enter arg6')]
    public string $arg6;

    #[Argument]
    #[Ask('Enter arg7')]
    public string $arg7;

    #[Argument]
    #[Ask('Enter arg8')]
    public bool $arg8;

    #[Argument]
    #[Ask('Enter arg9')]
    public array $arg9;

    #[Interact]
    public function prompt(SymfonyStyle $io): void
    {
        $this->arg7 ??= $io->ask('Enter arg7');
    }
}

enum Arg2: string {
    case ARG2_VALUE = 'arg2-value';
    case ARG22_VALUE = 'arg22-value';
}
