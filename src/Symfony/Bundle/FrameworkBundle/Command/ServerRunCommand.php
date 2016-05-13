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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Runs Symfony application using PHP built-in web server.
 *
 * @author Michał Pipa <michal.pipa.xsolve@gmail.com>
 */
class ServerRunCommand extends ServerCommand
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
            ))
            ->setName('server:run')
            ->setDescription('Runs PHP built-in web server')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs PHP built-in web server:

  <info>%command.full_name%</info>

To change default bind address and port use the <info>address</info> argument:

  <info>%command.full_name% 127.0.0.1:8080</info>

To change default docroot directory use the <info>--docroot</info> option:

  <info>%command.full_name% --docroot=htdocs/</info>

If you have custom docroot directory layout, you can specify your own
router script using <info>--router</info> option:

  <info>%command.full_name% --router=app/config/router.php</info>

Specifing a router script is required when the used environment is not "dev",
"prod", or "test".

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
        $io = new SymfonyStyle($input, $output);
        $documentRoot = $input->getOption('docroot');

        if (null === $documentRoot) {
            $documentRoot = $this->getContainer()->getParameter('kernel.root_dir').'/../web';
        }

        if (!is_dir($documentRoot)) {
            $io->error(sprintf('The given document root directory "%s" does not exist', $documentRoot));

            return 1;
        }

        $env = $this->getContainer()->getParameter('kernel.environment');
        $address = $input->getArgument('address');

        if (false === strpos($address, ':')) {
            $address = $address.':'.$input->getOption('port');
        }

        if ($this->isOtherServerProcessRunning($address)) {
            $io->error(sprintf('A process is already listening on http://%s.', $address));

            return 1;
        }

        if ('prod' === $env) {
            $io->error('Running PHP built-in server in production environment is NOT recommended!');
        }

        $io->success(sprintf('Server running on http://%s', $address));
        $io->comment('Quit the server with CONTROL-C.');

        if (null === $builder = $this->createPhpProcessBuilder($io, $address, $input->getOption('router'), $env)) {
            return 1;
        }

        $builder->setWorkingDirectory($documentRoot);
        $builder->setTimeout(null);
        $process = $builder->getProcess();

        if (OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
            $process->disableOutput();
        }

        $this
            ->getHelper('process')
            ->run($output, $process, null, null, OutputInterface::VERBOSITY_VERBOSE);

        if (!$process->isSuccessful()) {
            $errorMessages = array('Built-in server terminated unexpectedly.');

            if ($process->isOutputDisabled()) {
                $errorMessages[] = 'Run the command again with -v option for more details.';
            }

            $io->error($errorMessages);
        }

        return $process->getExitCode();
    }

    private function createPhpProcessBuilder(SymfonyStyle $io, $address, $router, $env)
    {
        $router = $router ?: $this
            ->getContainer()
            ->get('kernel')
            ->locateResource(sprintf('@FrameworkBundle/Resources/config/router_%s.php', $env))
        ;

        if (!file_exists($router)) {
            $io->error(sprintf('The given router script "%s" does not exist.', $router));

            return;
        }

        $router = realpath($router);
        $finder = new PhpExecutableFinder();

        if (false === $binary = $finder->find()) {
            $io->error('Unable to find PHP binary to run server.');

            return;
        }

        return new ProcessBuilder(array($binary, '-S', $address, $router));
    }
}
