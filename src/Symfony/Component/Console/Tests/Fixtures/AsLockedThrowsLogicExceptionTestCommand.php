<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\AsLockedCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'as:locked-throws:command',
)]
#[AsLockedCommand]
class AsLockedThrowsLogicExceptionTestCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->createLock($input);

        return Command::SUCCESS;
    }
}
