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
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('invokable:ask:readonly-input')]
class InvokableWithReadonlyAskInputCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] ReadonlyAskInputDto $input): int
    {
        $io->writeln(\sprintf('Name: %s', $input->name));

        return Command::SUCCESS;
    }
}

final class ReadonlyAskInputDto
{
    #[Argument]
    #[Ask('Enter a name')]
    public readonly string $name;
}
