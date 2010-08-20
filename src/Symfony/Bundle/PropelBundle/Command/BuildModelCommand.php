<?php

namespace Symfony\Bundle\PropelBundle\Command;

use Symfony\Bundle\PropelBundle\Command\PhingCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BuildCommand.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuildModelCommand extends PhingCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Build the Propel Object Model classes based on XML schemas')
            ->setHelp(<<<EOT
The <info>propel:build-model</info> command builds the Propel runtime model classes (ActiveRecord, Query, Peer, and TableMap classes) based on the XML schemas defined in all Bundles:

  <info>./symfony propel:build-model</info>
EOT
            )
            ->setName('propel:build-model')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->callPhing('om');
    }

}
