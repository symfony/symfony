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
use Symfony\Component\Validator\Constraints\File;

#[AsCommand(name: 'app:input-file-with-constraints')]
class InvokableWithInputFileAndConstraintsTestCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument]
        #[Ask('Provide an image file:', constraints: [new File(mimeTypes: ['image/png', 'image/jpeg'])])]
        InputFile $file,
    ): int {
        $output->writeln('Filename: '.$file->getFilename());
        $output->writeln('Valid: '.($file->isValid() ? 'yes' : 'no'));

        return Command::SUCCESS;
    }
}
