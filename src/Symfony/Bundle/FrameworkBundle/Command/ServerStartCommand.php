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
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', '127.0.0.1:8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', null),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
            ))
            ->setName('server:start')
            ->setDescription('Starts PHP built-in web server in the background')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs PHP's built-in web server:

  <info>%command.full_name%</info>

To change the default bind address and the default port use the <info>address</info> argument:

  <info>%command.full_name% 127.0.0.1:8080</info>

To change the default document root directory use the <info>--docroot</info> option:

  <info>%command.full_name% --docroot=htdocs/</info>

If you have a custom document root directory layout, you can specify your own
router script using the <info>--router</info> option:

  <info>%command.full_name% --router=app/config/router.php</info>

Specifying a router script is required when the used environment is not "dev" or
"prod".

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

        if ('prod' === $env) {
            $output->writeln('<error>Running PHP built-in server in production environment is NOT recommended!</error>');
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            $output->writeln('<error>Unable to start the server process</error>');

            return 1;
        }

        $address = $input->getArgument('address');

        if ($pid > 0) {
            $output->writeln(sprintf('<info>Web server listening on http://%s</info>', $address));

            return;
        }

        if (posix_setsid() < 0) {
            $output->writeln('<error>Unable to set the child process as session leader</error>');

            return 1;
        }

        if (null === $process = $this->createServerProcess($output, $address, $documentRoot, $input->getOption('router'), $env, null)) {
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
     * Creates a process to start PHP's built-in web server.
     *
     * @param OutputInterface $output       A OutputInterface instance
     * @param string          $address      IP address and port to listen to
     * @param string          $documentRoot The application's document root
     * @param string          $router       The router filename
     * @param string          $env          The application environment
     * @param int             $timeout      Process timeout
     *
     * @return Process The process
     */
    private function createServerProcess(OutputInterface $output, $address, $documentRoot, $router, $env, $timeout = null)
    {
        $router = $router ?: $this
            ->getContainer()
            ->get('kernel')
            ->locateResource(sprintf('@FrameworkBundle/Resources/config/router_%s.php', $env))
        ;

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

        return new Process('exec '.$script, $documentRoot, null, null, $timeout);
    }
}
