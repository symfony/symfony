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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateHydratorsCommand;

/**
 * Generate the Doctrine ORM document hydrators to your cache directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateHydratorsDoctrineODMCommand extends GenerateHydratorsCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:mongodb:generate:hydrators')
            ->addOption('dm', null, InputOption::VALUE_OPTIONAL, 'The document manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:mongodb:generate:hydrators</info> command generates hydrator classes for your documents:

  <info>./app/console doctrine:mongodb:generate:hydrators</info>

You can specify the document manager you want to generate the hydrators for:

  <info>./app/console doctrine:mongodb:generate:hydrators --dm=name</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineODMCommand::setApplicationDocumentManager($this->getApplication(), $input->getOption('dm'));

        return parent::execute($input, $output);
    }
}
