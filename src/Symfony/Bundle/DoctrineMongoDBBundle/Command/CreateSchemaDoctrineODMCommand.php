<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\CreateCommand;

/**
 * Command to create the database schema for a set of classes based on their mappings.
 *
 * @author     Justin Hileman <justin@shopopensky.com>
 */
class CreateSchemaDoctrineODMCommand extends CreateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:odm:schema:create')
            ->addOption('dm', null, InputOption::PARAMETER_REQUIRED, 'The document manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:odm:schema:create</info> command creates the default document manager's schema:

  <info>./symfony doctrine:odm:schema:create</info>

You can also optionally specify the name of a document manager to create the schema for:

  <info>./symfony doctrine:odm:schema:create --dm=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineODMCommand::setApplicationDocumentManager($this->application, $input->getOption('dm'));

        parent::execute($input, $output);
    }
}