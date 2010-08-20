<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command as BaseCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Command extends BaseCommand
{
    protected $container;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->container = $this->application->getKernel()->getContainer();
    }
}
