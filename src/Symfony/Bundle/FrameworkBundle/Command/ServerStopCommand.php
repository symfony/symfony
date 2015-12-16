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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stops a background process running PHP's built-in web server.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStopCommand extends ServerCommand
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
            ))
            ->setName('server:stop')
            ->setDescription('Stops PHP\'s built-in web server that was started with the server:start command')
            ->setHelp(<<<EOF
The <info>%command.name%</info> stops PHP's built-in web server:

  <info>php %command.full_name%</info>

To change the default bind address and the default port use the <info>address</info> argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

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

        $address = $input->getArgument('address');
        if (false === strpos($address, ':')) {
            $address = $address.':'.$input->getOption('port');
        }

        $lockFile = $this->getLockFile($address);

        if (!file_exists($lockFile)) {
            $io->error(sprintf('No web server is listening on http://%s', $address));

            return 1;
        }

        unlink($lockFile);
        $io->success(sprintf('Stopped the web server listening on http://%s', $address));
    }
}
