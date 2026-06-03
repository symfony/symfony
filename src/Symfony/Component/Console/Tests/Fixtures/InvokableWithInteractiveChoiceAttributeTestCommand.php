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

#[AsCommand('invokable:interactive:choice')]
class InvokableWithInteractiveChoiceAttributeTestCommand
{
    public function __invoke(
        SymfonyStyle $io,

        #[Argument, AskChoice('Select a color', ['red', 'green', 'blue'])]
        string $color,

        #[MapInput]
        ChoiceDto $dto,
    ): int {
        $io->writeln('Color: '.$color);
        $io->writeln('Size: '.$dto->size);
        $io->writeln('Status: '.$dto->status->value);
        $io->writeln('Features: '.implode(',', $dto->features));

        return Command::SUCCESS;
    }
}

class ChoiceDto
{
    #[Argument]
    #[AskChoice('Select a size', ['small', 'medium', 'large'], default: 'medium')]
    public string $size;

    #[Argument]
    #[AskChoice('Select a status')]
    public ChoiceStatus $status;

    #[Argument]
    #[AskChoice('Select features', ['auth', 'api', 'cache'])]
    public array $features;
}

enum ChoiceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}
