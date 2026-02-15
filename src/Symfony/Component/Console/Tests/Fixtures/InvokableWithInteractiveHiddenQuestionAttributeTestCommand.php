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

#[AsCommand('invokable:interactive:question')]
class InvokableWithInteractiveHiddenQuestionAttributeTestCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[MapInput] DtoWithHiddenQuestionArg $dto,
    ): int {
        $io->writeln('Arg1: '.$dto->arg1);

        return Command::SUCCESS;
    }
}

class DtoWithHiddenQuestionArg
{
    #[Argument]
    #[Ask('Enter arg1', hidden: true)]
    public string $arg1;
}
