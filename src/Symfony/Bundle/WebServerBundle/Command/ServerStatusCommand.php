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
use Symfony\Component\Console\Input\InputArgument;
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
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)', '127.0.0.1:8000'),
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
        $server = new WebServer($input->getArgument('addressport'));
        if ($server->isRunning()) {
            $io->success(sprintf('Web server still listening on http://%s', $server->getAddress()));
        } else {
            $io->warning(sprintf('No web server is listening on http://%s', $server->getAddress()));
        }
    }
}
