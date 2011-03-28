<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ODM\MongoDB\Tools\Console\Command\ClearCache\MetadataCommand;

/**
 * Command to clear the metadata cache of the various cache drivers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author  Henrik Westphal <henrik.westphal@gmail.com>
 */
class ClearMetadataCacheDoctrineODMCommand extends MetadataCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:mongodb:cache:clear-metadata')
            ->setDescription('Clear all metadata cache for a document manager.')
            ->addOption('dm', null, InputOption::VALUE_OPTIONAL, 'The document manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:mongodb:cache:clear-metadata</info> command clears all metadata cache for the default document manager:

  <info>./app/console doctrine:mongodb:cache:clear-metadata</info>

You can also optionally specify the <comment>--dm</comment> option to specify which document manager to clear the cache for:

  <info>./app/console doctrine:mongodb:cache:clear-metadata --dm=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineODMCommand::setApplicationDocumentManager($this->getApplication(), $input->getOption('dm'));

        return parent::execute($input, $output);
    }
}