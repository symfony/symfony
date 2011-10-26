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
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand as DoctrineValidateSchemaCommand;

/**
 * Command to run Doctrine ValidateSchema() on the current mappings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Neil Katin <symfony@askneil.com>
 */
class ValidateSchemaCommand extends DoctrineValidateSchemaCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:validate')
            ->setDescription('Validate the doctrine mapping files')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command')
            ->setHelp(<<<EOT
The <info>doctrine:schema:validate</info> checks the current mappings
for valid forward and reverse mappings.

<info>php app/console doctrine:schema:validate</info>

You can also optionally specify the <comment>--em</comment> option to specify
which entity manager use for the validation.

<info>php app/console doctrine:schema:validate --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
