<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kernel:hello')]
final class KernelCommand extends MinimalKernel
{
    public function __invoke(OutputInterface $output): int
    {
        $output->write('Hello Kernel!');

        return 0;
    }
}
