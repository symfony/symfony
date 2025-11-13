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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractLoopCommand extends Command
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $contexts = [1, 2, 3];
        $io->progressStart(count($contexts));
        $code = self::SUCCESS;

        foreach ($contexts as $ignored) {
            $io->progressAdvance();
            try {
                parent::run($input, $output);
            } catch (\Throwable) {
                $code = self::FAILURE;
            }
        }
        $io->progressFinish();
        $output->writeln("\nLoop finished.");

        return $code;
    }
}
