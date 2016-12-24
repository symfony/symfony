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
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', null),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
            ))
            ->setDescription('Starts a local web server in the background')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs a local web server:

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

        $router = $input->getOption('router');

        try {
            $server = new WebServer($input->getArgument('addressport'));
            $server->setConfig($documentRoot, $env);

            if (WebServer::STARTED === $server->start($router)) {
                $io->success(sprintf('Server listening on http://%s', $server->getAddress()));
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }
    }
}
