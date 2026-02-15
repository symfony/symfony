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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:cmd0')]
class MethodBasedTestCommand
{
    public function __invoke(OutputInterface $o): int
    {
        $o->write('cmd0');

        return Command::SUCCESS;
    }

    #[AsCommand('app:cmd1')]
    public function cmd1(OutputInterface $o, #[Argument] ?string $name = null): int
    {
        $o->write('cmd1');

        return Command::SUCCESS;
    }

    #[AsCommand('app:cmd2')]
    public function cmd2(OutputInterface $o): int
    {
        $o->write('cmd2');

        return Command::SUCCESS;
    }
}
