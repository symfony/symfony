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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;

/**
 * Command to update the database schema for a set of classes based on their mappings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class UpdateSchemaDoctrineCommand extends UpdateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:update')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:schema:update</info> command updates the default entity managers schema:

  <info>./app/console doctrine:schema:update</info>

You can also optionally specify the name of a entity manager to update the schema for:

  <info>./app/console doctrine:schema:update --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        parent::execute($input, $output);
    }
}