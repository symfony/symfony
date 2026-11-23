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
use Symfony\Component\Console\Attribute\AskChoice;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('invokable:ask-choice:typed-input')]
class InvokableWithTypedAskChoiceInputCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] TypedAskChoiceInputDto $input): int
    {
        $io->writeln(\sprintf('Color: %s, count: %d', $input->color, $input->count));

        return Command::SUCCESS;
    }
}

class TypedAskChoiceInputDto
{
    #[Argument]
    #[AskChoice('Select a color', ['red', 'green'])]
    public string $color;

    #[Argument]
    #[AskChoice('Select a count', [1, 2, 3])]
    public int $count;
}
