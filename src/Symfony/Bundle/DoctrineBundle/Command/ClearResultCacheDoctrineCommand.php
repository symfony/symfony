<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command to clear the result cache of the various cache drivers.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearResultCacheDoctrineCommand extends ResultCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-result')
            ->setDescription('Clear result cache for a entity manager.')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-result</info> command clears all result cache for the default entity manager:

  <info>./symfony doctrine:cache:clear-result</info>

You can also optionally specify the <comment>--em</comment> option to specify which entity manager to clear the cache for:

  <info>./symfony doctrine:cache:clear-result --em=default</info>

If you don't want to clear all result cache you can specify some additional options to control what cache is deleted:

    <info>./symfony doctrine:cache:clear-result --id=cache_key</info>

Or you can specify a <comment>--regex</comment> to delete cache entries that match it:

    <info>./symfony doctrine:cache:clear-result --regex="user_(.*)"</info>

You can also specify a <comment>--prefix</comment> or <comment>--suffix</comment> to delete cache entries for:

    <info>./symfony doctrine:cache:clear-result --prefix="user_" --suffix="_frontend"</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        return parent::execute($input, $output);
    }
}