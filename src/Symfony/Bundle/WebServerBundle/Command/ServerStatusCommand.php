<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Command;

use Symfony\Bundle\WebServerBundle\WebServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the status of a process that is running PHP's built-in web server in
 * the background.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStatusCommand extends ServerCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('server:status')
            ->setDefinition(array(
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ))
            ->setDescription('Outputs the status of the local web server for the given address')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $server = new WebServer();
        if ($server->isRunning($input->getOption('pidfile'))) {
            $io->success('Web server still listening.');
        } else {
            $io->warning('No web server is listening.');
        }
    }
}
