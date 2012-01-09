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
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;

/**
 * Command to clear the query cache of the various cache drivers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearQueryCacheDoctrineCommand extends QueryCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-query')
            ->setDescription('Clears all query cache for a entity manager')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command')
            ->setHelp(<<<EOT
The <info>doctrine:cache:clear-query</info> command clears all query cache for
the default entity manager:

<info>php app/console doctrine:cache:clear-query</info>

You can also optionally specify the <comment>--em</comment> option to specify
which entity manager to clear the cache for:

<info>php app/console doctrine:cache:clear-query --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
