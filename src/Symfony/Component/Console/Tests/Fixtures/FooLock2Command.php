<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FooLock2Command extends Command
{
    use LockableTrait;

    protected function configure(): void
    {
        $this->setName('foo:lock2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->lock();
            $this->lock();
        } catch (LogicException $e) {
            return 1;
        }

        return 2;
    }
}
