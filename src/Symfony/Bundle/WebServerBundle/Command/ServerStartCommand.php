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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs a local web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStartCommand extends ServerCommand
{
    private $documentRoot;
    private $environment;

    public function __construct($documentRoot = null, $environment = null)
    {
        $this->documentRoot = $documentRoot;
        $this->environment = $environment;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('server:start')
            ->setDefinition(array(
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ))
            ->setDescription('Starts a local web server in the background')
            ->setHelp(<<<'EOF'
<info>%command.name%</info> runs a local web server: By default, the server
listens on <comment>127.0.0.1</> address and the port number is automatically selected
as the first free port starting from <comment>8000</>:

  <info>php %command.full_name%</info>

The server is run in the background and you can keep executing other commands.
Execute <comment>server:stop</> to stop it.

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
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (!extension_loaded('pcntl')) {
            $io->error(array(
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ));

            if ($io->confirm('Do you want to execute <info>server:run</info> immediately?', false)) {
                return $this->getApplication()->find('server:run')->run($input, $output);
            }

            return 1;
        }

        // deprecated, logic to be removed in 4.0
        // this allows the commands to work out of the box with web/ and public/
        if ($this->documentRoot && !is_dir($this->documentRoot) && is_dir(dirname($this->documentRoot).'/web')) {
            $this->documentRoot = dirname($this->documentRoot).'/web';
        }

        if (null === $documentRoot = $input->getOption('docroot')) {
            if (!$this->documentRoot) {
                $io->error('The document root directory must be either passed as first argument of the constructor or through the "docroot" input option.');

                return 1;
            }
            $documentRoot = $this->documentRoot;
        }

        if (!$env = $this->environment) {
            if ($input->hasOption('env') && !$env = $input->getOption('env')) {
                $io->error('The environment must be either passed as second argument of the constructor or through the "--env" input option.');

                return 1;
            } else {
                $io->error('The environment must be passed as second argument of the constructor.');

                return 1;
            }
        }

        if ('prod' === $env) {
            $io->error('Running this server in production environment is NOT recommended!');
        }

        try {
            $server = new WebServer();
            if ($server->isRunning($input->getOption('pidfile'))) {
                $io->error(sprintf('The web server is already running (listening on http://%s).', $server->getAddress($input->getOption('pidfile'))));

                return 1;
            }

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
