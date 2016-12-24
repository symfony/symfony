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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs Symfony application using a local web server.
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
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)', '127.0.0.1:8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', null),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script', dirname(__DIR__).'/Resources/router.php'),
            ))
            ->setName('server:run')
            ->setDescription('Runs a local web server')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs a local web server:

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

        $router = $input->getOption('router');

        $callback = null;
        $disableOutput = false;
        if ($output->isQuiet()) {
            $disableOutput = true;
        } else {
            $callback = function ($type, $buffer) use ($output) {
                if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            };
        }

        try {
            $server = new WebServer($input->getArgument('addressport'));
            $server->setConfig($documentRoot, $env);

            $io->success(sprintf('Server listening on http://%s', $server->getAddress()));
            $io->comment('Quit the server with CONTROL-C.');

            $exitCode = $server->run($router, $disableOutput, $callback);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        return $exitCode;
    }
}
