<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Convert Doctrine ORM metadata mapping information between the various supported
 * formats.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ConvertMappingDoctrineCommand extends ConvertMappingCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('doctrine:mapping:convert')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:mapping:convert</info> command converts mapping information between supported formats:

  <info>./symfony doctrine:mapping:convert xml /path/to/output</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        return parent::execute($input, $output);
    }
}