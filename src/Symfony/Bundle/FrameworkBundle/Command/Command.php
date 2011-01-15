<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Command.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Command extends BaseCommand
{
    protected $container;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->container = $this->application->getKernel()->getContainer();
    }
}
