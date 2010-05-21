<?php

namespace Symfony\Framework\DoctrineMigrationsBundle\Command;

use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Input\InputOption;
use Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command for generating new blank migration classes
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class MigrationsGenerateDoctrineCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:migrations:generate')
            ->addOption('bundle', null, InputOption::PARAMETER_REQUIRED, 'The bundle to load migrations configuration from.')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        $configuration = $this->_getMigrationConfiguration($input, $output);
        DoctrineCommand::configureMigrationsForBundle($this->application, $input->getOption('bundle'), $configuration);

        parent::execute($input, $output);
    }
}