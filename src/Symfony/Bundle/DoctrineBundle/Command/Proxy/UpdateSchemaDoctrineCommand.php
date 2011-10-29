<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;

/**
 * Command to generate the SQL needed to update the database schema to match
 * the current mapping information.
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
            ->setDescription('Executes (or dumps) the SQL needed to update the database schema to match the current mapping metadata')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command')
            ->setHelp(<<<EOT
The <info>doctrine:schema:update</info> command generates the SQL needed to
synchronize the database schema with the current mapping metadata of the
default entity manager.

For example, if you add metadata for a new column to an entity, this command
would generate and output the SQL needed to add the new column to the database:

<info>php app/console doctrine:schema:update --dump-sql</info>

Alternatively, you can execute the generated queries:

<info>php app/console doctrine:schema:update --force</info>

You can also update the database schema for a specific entity manager:

<info>php app/console doctrine:schema:update --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        parent::execute($input, $output);
    }
}
