<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:validated-input', description: 'Tests validated MapInput')]
class ValidatedInputCommand
{
    public function __invoke(
        OutputInterface $output,
        #[MapInput]
        ValidatedInput $input,
    ): int {
        $output->writeln('Name: '.$input->name);
        $output->writeln('Email: '.$input->email);

        return Command::SUCCESS;
    }
}
