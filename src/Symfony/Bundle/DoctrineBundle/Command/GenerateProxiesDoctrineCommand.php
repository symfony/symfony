<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Generate the Doctrine ORM entity proxies to your cache directory.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateProxiesDoctrineCommand extends GenerateProxiesCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:generate:proxies')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:generate:proxies</info> command generates proxy classes for your entities:

  <info>./symfony doctrine:generate:proxies</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        return parent::execute($input, $output);
    }
}