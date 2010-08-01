<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Ensure the Doctrine ORM is configured properly for a production environment.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class EnsureProductionSettingsDoctrineCommand extends EnsureProductionSettingsCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:ensure-production-settings')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-metadata</info> command clears all metadata cache for the default entity manager:

  <info>./symfony doctrine:cache:clear-metadata</info>

You can also optionally specify the <comment>--em</comment> option to specify which entity manager to clear the cache for:

  <info>./symfony doctrine:cache:clear-metadata --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        parent::execute($input, $output);
    }
}