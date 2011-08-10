<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Clear log files
 *
 * @author Luis Cordova <cordoval@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MonologClearCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('monolog:clear')
            ->setDescription('Clear the logs')
            ->setHelp(<<<EOF
The <info>monolog:clear</info> command clears the application logs for all environments:

<info>php app/console monolog:clear</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $realLogDir = $this->getContainer()->getParameter('kernel.logs_dir');
        $oldLogDir  = $realLogDir.'_old';

        if (!is_writable($realLogDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realLogDir));
        }

        $output->writeln(sprintf('Clearing the logs.'));

        rename($realLogDir, $oldLogDir);

        $this->getContainer()->get('filesystem')->remove($oldLogDir);
        $this->getContainer()->get('filesystem')->mkdir($realLogDir);
    }

}
