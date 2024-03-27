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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'as:callable-locked:command',
)]
#[AsLockedCommand(
    lock: [self::class, 'getLockKey']
)]
class AsLockedWithCallableTestCommand extends Command
{
    public function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }

    public static function getLockKey(InputInterface $input): string
    {
        return sprintf('lock-%s', $input->getArgument('key'));
    }
}
