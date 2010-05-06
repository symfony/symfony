<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command to clear the query cache of the various cache drivers.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearQueryCacheDoctrineCommand extends QueryCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-query')
            ->setDescription('Clear all query cache for a entity manager.')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-query</info> command clears all query cache for the default entity manager:

  <info>./symfony doctrine:cache:clear-query</info>

You can also optionally specify the <comment>--em</comment> option to specify which entity manager to clear the cache for:

  <info>./symfony doctrine:cache:clear-query --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        return parent::execute($input, $output);
    }
}