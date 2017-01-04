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
use Symfony\Bundle\WebServerBundle\WebServerConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs a local web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStartCommand extends ServerCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('server:start')
            ->setDefinition(array(
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)', '127.0.0.1:8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ))
            ->setDescription('Starts a local web server in the background')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs a local web server:

  <info>php %command.full_name%</info>

Change the default address and port by passing them as an argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

Use the <info>--docroot</info> option to change the default docroot directory:

  <info>php %command.full_name% --docroot=htdocs/</info>

Specify your own router script via the <info>--router</info> option:

  <info>php %command.full_name% --router=app/config/router.php</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $cliOutput = $output);

        if (!extension_loaded('pcntl')) {
            $io->error(array(
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ));

            if ($io->ask('Do you want to execute <info>server:run</info> immediately? [Yn] ', true)) {
                return $this->getApplication()->find('server:run')->run($input, $cliOutput);
            }

            return 1;
        }

        if (null === $documentRoot = $input->getOption('docroot')) {
            $documentRoot = $this->getContainer()->getParameter('kernel.root_dir').'/../web';
        }

        if (!is_dir($documentRoot)) {
            $io->error(sprintf('The document root directory "%s" does not exist.', $documentRoot));

            return 1;
        }

        $env = $this->getContainer()->getParameter('kernel.environment');
        if ('prod' === $env) {
            $io->error('Running this server in production environment is NOT recommended!');
        }

        try {
            $server = new WebServer();
            $config = new WebServerConfig($documentRoot, $env, $input->getArgument('addressport'), $input->getOption('router'));

            if (WebServer::STARTED === $server->start($config, $input->getOption('pidfile'))) {
                $io->success(sprintf('Server listening on http://%s', $config->getAddress()));
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }
    }
}
