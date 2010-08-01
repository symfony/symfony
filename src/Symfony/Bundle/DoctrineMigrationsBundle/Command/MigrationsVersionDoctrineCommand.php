<?php

namespace Symfony\Bundle\DoctrineMigrationsBundle\Command;

use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Input\InputOption;
use Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command for manually adding and deleting migration versions from the version table.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class MigrationsVersionDoctrineCommand extends VersionCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:migrations:version')
            ->addOption('bundle', null, InputOption::PARAMETER_REQUIRED, 'The bundle to load migrations configuration from.')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        parent::execute($input, $output);
    }
}