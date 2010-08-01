<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command to update the database schema for a set of classes based on their mappings.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class UpdateSchemaDoctrineCommand extends UpdateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:update')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:schema:update</info> command updates the default entity managers schema:

  <info>./symfony doctrine:schema:update</info>

You can also optionally specify the name of a entity manager to update the schema for:

  <info>./symfony doctrine:schema:update --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        parent::execute($input, $output);
    }
}