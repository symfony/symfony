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
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:input-files-variadic')]
class InvokableWithInputFilesVariadicTestCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument]
        #[Ask('Provide files:')]
        InputFile ...$files,
    ): int {
        $output->writeln('Count: '.\count($files));

        foreach ($files as $file) {
            $output->writeln('Filename: '.$file->getFilename());
        }

        return Command::SUCCESS;
    }
}
