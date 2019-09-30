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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Runs a local web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * @deprecated since Symfony 4.4, to be removed in 5.0; the new Symfony local server has more features, you can use it instead.
 */
class ServerStartCommand extends Command
{
    private $documentRoot;
    private $environment;
    private $pidFileDirectory;

    protected static $defaultName = 'server:start';

    public function __construct(string $documentRoot = null, string $environment = null, string $pidFileDirectory = null)
    {
        $this->documentRoot = $documentRoot;
        $this->environment = $environment;
        $this->pidFileDirectory = $pidFileDirectory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ])
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

See also: https://php.net/features.commandline.webserver
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        @trigger_error('Using the WebserverBundle is deprecated since Symfony 4.4. The new Symfony local server has more features, you can use it instead.', E_USER_DEPRECATED);

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (!\extension_loaded('pcntl')) {
            $io->error([
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ]);

            if ($io->confirm('Do you want to execute <info>server:run</info> immediately?', false)) {
                return $this->getApplication()->find('server:run')->run($input, $output);
            }

            return 1;
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

        // replace event dispatcher with an empty one to prevent console.terminate from firing
        // as container could have changed between start and stop
        $this->getApplication()->setDispatcher(new EventDispatcher());

        try {
            $server = new WebServer($this->pidFileDirectory);
            if ($server->isRunning($input->getOption('pidfile'))) {
                $io->error(sprintf('The web server has already been started. It is currently listening on http://%s. Please stop the web server before you try to start it again.', $server->getAddress($input->getOption('pidfile'))));

                return 1;
            }

            $config = new WebServerConfig($documentRoot, $env, $input->getArgument('addressport'), $input->getOption('router'));

            if (WebServer::STARTED === $server->start($config, $input->getOption('pidfile'))) {
                $message = sprintf('Server listening on http://%s', $config->getAddress());
                if ('' !== $displayAddress = $config->getDisplayAddress()) {
                    $message = sprintf('Server listening on all interfaces, port %s -- see http://%s', $config->getPort(), $displayAddress);
                }
                $io->success($message);
                if (ini_get('xdebug.profiler_enable_trigger')) {
                    $io->comment('Xdebug profiler trigger enabled.');
                }
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
