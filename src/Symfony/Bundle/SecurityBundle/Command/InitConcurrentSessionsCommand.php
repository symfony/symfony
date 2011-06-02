<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Symfony\Bundle\DoctrineBundle\Command\Proxy\DoctrineCommandHelper;

/**
 * Installs the database schema required by the concurrent session Doctrine implementation
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class InitConcurrentSessionsCommand extends CreateCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('init:concurrent-session')
            ->setDescription('Executes the SQL needed to generate the database schema reqired by the concurrent sessions feature.')
            ->setHelp(<<<EOT
The <info>init:concurrent-session</info> command executes the SQL needed to
generate the database schema required by the concurrent session Doctrine implementation:

<info>./app/console init:concurrent-session</info>

You can also output the SQL instead of executing it:

<info>./app/console init:concurrent-session --dump-sql</info>
EOT
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), 'security');

        parent::execute($input, $output);
    }
}
