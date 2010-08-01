<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Execute a SQL query and output the results.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class RunSqlDoctrineCommand extends RunSqlCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:query:sql')
            ->addOption('connection', null, InputOption::PARAMETER_OPTIONAL, 'The connection to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:query:sql</info> command executes the given DQL query and outputs the results:

  <info>./symfony doctrine:query:sql "SELECT * from user"</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationConnection($this->application, $input->getOption('connection'));

        return parent::execute($input, $output);
    }
}