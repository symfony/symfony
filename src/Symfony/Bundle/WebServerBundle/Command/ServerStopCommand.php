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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stops a background process running a local web server.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStopCommand extends Command
{
    protected static $defaultName = 'server:stop';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ])
            ->setDescription('Stops the local web server that was started with the server:start command')
            ->setHelp(<<<'EOF'
<info>%command.name%</info> stops the local web server:

  <info>php %command.full_name%</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        try {
            $server = new WebServer();
            $server->stop($input->getOption('pidfile'));
            $io->success('Stopped the web server.');
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }
    }
}
