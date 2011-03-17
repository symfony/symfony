<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;

/**
 * Command to clear the result cache of the various cache drivers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearResultCacheDoctrineCommand extends ResultCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-result')
            ->setDescription('Clear result cache for a entity manager.')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-result</info> command clears all result cache for the default entity manager:

  <info>./app/console doctrine:cache:clear-result</info>

You can also optionally specify the <comment>--em</comment> option to specify which entity manager to clear the cache for:

  <info>./app/console doctrine:cache:clear-result --em=default</info>

If you don't want to clear all result cache you can specify some additional options to control what cache is deleted:

    <info>./app/console doctrine:cache:clear-result --id=cache_key</info>

Or you can specify a <comment>--regex</comment> to delete cache entries that match it:

    <info>./app/console doctrine:cache:clear-result --regex="user_(.*)"</info>

You can also specify a <comment>--prefix</comment> or <comment>--suffix</comment> to delete cache entries for:

    <info>./app/console doctrine:cache:clear-result --prefix="user_" --suffix="_frontend"</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}