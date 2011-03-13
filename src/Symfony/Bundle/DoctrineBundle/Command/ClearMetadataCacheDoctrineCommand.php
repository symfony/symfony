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
use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;

/**
 * Command to clear the metadata cache of the various cache drivers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearMetadataCacheDoctrineCommand extends MetadataCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-metadata')
            ->setDescription('Clear all metadata cache for a entity manager.')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-metadata</info> command clears all metadata cache for the default entity manager:

  <info>./app/console doctrine:cache:clear-metadata</info>

You can also optionally specify the <comment>--em</comment> option to specify which entity manager to clear the cache for:

  <info>./app/console doctrine:cache:clear-metadata --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}