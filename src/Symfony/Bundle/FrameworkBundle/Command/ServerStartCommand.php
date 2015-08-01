<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs PHP's built-in web server in a background process.
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
            ->setDefinition(array(
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', '127.0.0.1'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Address port number', '8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', null),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force web server startup'),
            ))
            ->setName('server:start')
            ->setDescription('Starts PHP built-in web server in the background')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs PHP's built-in web server:

  <info>php %command.full_name%</info>

To change the default bind address and the default port use the <info>address</info> argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

To change the default document root directory use the <info>--docroot</info> option:

  <info>php %command.full_name% --docroot=htdocs/</info>

If you have a custom document root directory layout, you can specify your own
router script using the <info>--router</info> option:

  <info>php %command.full_name% --router=app/config/router.php</info>

Specifying a router script is required when the used environment is not <comment>"dev"</comment> or
<comment>"prod"</comment>.

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
        if (!extension_loaded('pcntl')) {
            $output->writeln('<error>This command needs the pcntl extension to run.</error>');
            $output->writeln('You can either install it or use the <info>server:run</info> command instead to run the built-in web server.');

            if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('Do you want to start <info>server:run</info> immediately? [Yn] ', true))) {
                $command = $this->getApplication()->find('server:run');

                return $command->run($input, $output);
            }

            return 1;
        }

        $documentRoot = $input->getOption('docroot');

        if (null === $documentRoot) {
            $documentRoot = $this->getContainer()->getParameter('kernel.root_dir').'/../web';
        }

        if (!is_dir($documentRoot)) {
            $output->writeln(sprintf('<error>The given document root directory "%s" does not exist</error>', $documentRoot));

            return 1;
        }

        $env = $this->getContainer()->getParameter('kernel.environment');

        if (false === $router = $this->determineRouterScript($input->getOption('router'), $env, $output)) {
            return 1;
        }

        $address = $input->getArgument('address');

        if (false === strpos($address, ':')) {
            $address = $address.':'.$input->getOption('port');
        }

        if (!$input->getOption('force') && $this->isOtherServerProcessRunning($address)) {
            $output->writeln(sprintf('<error>A process is already listening on http://%s.</error>', $address));
            $output->writeln(sprintf('<error>Use the --force option if the server process terminated unexpectedly to start a new web server process.</error>'));

            return 1;
        }

        if ('prod' === $env) {
            $output->writeln('<error>Running PHP built-in server in production environment is NOT recommended!</error>');
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            $output->writeln('<error>Unable to start the server process</error>');

            return 1;
        }

        if ($pid > 0) {
            $output->writeln(sprintf('<info>Web server listening on http://%s</info>', $address));

            return;
        }

        if (posix_setsid() < 0) {
            $output->writeln('<error>Unable to set the child process as session leader</error>');

            return 1;
        }

        if (null === $process = $this->createServerProcess($output, $address, $documentRoot, $router)) {
            return 1;
        }

        $process->disableOutput();
        $process->start();
        $lockFile = $this->getLockFile($address);
        touch($lockFile);

        if (!$process->isRunning()) {
            $output->writeln('<error>Unable to start the server process</error>');
            unlink($lockFile);

            return 1;
        }

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($lockFile)) {
                $process->stop();
            }

            sleep(1);
        }
    }

    /**
     * Determine the absolute file path for the router script, using the environment to choose a standard script
     * if no custom router script is specified.
     *
     * @param string|null     $router File path of the custom router script, if set by the user; otherwise null
     * @param string          $env    The application environment
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return string|bool The absolute file path of the router script, or false on failure
     */
    private function determineRouterScript($router, $env, OutputInterface $output)
    {
        if (null === $router) {
            $router = $this
                ->getContainer()
                ->get('kernel')
                ->locateResource(sprintf('@FrameworkBundle/Resources/config/router_%s.php', $env))
            ;
        }

        if (false === $path = realpath($router)) {
            $output->writeln(sprintf('<error>The given router script "%s" does not exist</error>', $router));

            return false;
        }

        return $path;
    }

    /**
     * Creates a process to start PHP's built-in web server.
     *
     * @param OutputInterface $output       A OutputInterface instance
     * @param string          $address      IP address and port to listen to
     * @param string          $documentRoot The application's document root
     * @param string          $router       The router filename
     *
     * @return Process The process
     */
    private function createServerProcess(OutputInterface $output, $address, $documentRoot, $router)
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            $output->writeln('<error>Unable to find PHP binary to start server</error>');

            return;
        }

        $script = implode(' ', array_map(array('Symfony\Component\Process\ProcessUtils', 'escapeArgument'), array(
            $binary,
            '-S',
            $address,
            $router,
        )));

        return new Process('exec '.$script, $documentRoot, null, null, null);
    }
}
