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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('invokable:ask')]
class InvokableWithAskCommand
{
    public function __invoke(
        SymfonyStyle $io,

        #[Argument]
        #[Ask('What is your name?')]
        string $name,
    ): int {
        $io->writeln('Hello '.$name);

        return Command::SUCCESS;
    }
}
