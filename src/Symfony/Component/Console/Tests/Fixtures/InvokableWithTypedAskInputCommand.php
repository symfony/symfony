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

#[AsCommand('invokable:ask:typed-input')]
class InvokableWithTypedAskInputCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] TypedAskInputDto $input): int
    {
        $io->writeln(\sprintf('Name: %s, count: %d, ratio: %s', $input->name, $input->count, $input->ratio));

        return Command::SUCCESS;
    }
}

class TypedAskInputDto
{
    #[Argument]
    #[Ask('Enter a name')]
    public string $name;

    #[Argument]
    #[Ask('Enter a count')]
    public int $count;

    #[Argument]
    #[Ask('Enter a ratio')]
    public float $ratio;
}
