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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the status of a process that is running PHP's built-in web server in
 * the background.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * @deprecated since Symfony 4.4, to be removed in 5.0; the new Symfony local server has more features, you can use it instead.
 */
class ServerStatusCommand extends Command
{
    protected static $defaultName = 'server:status';

    private $pidFileDirectory;

    public function __construct(string $pidFileDirectory = null)
    {
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
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
                new InputOption('filter', null, InputOption::VALUE_REQUIRED, 'The value to display (one of port, host, or address)'),
            ])
            ->setDescription('Outputs the status of the local web server')
            ->setHelp(<<<'EOF'
<info>%command.name%</info> shows the details of the given local web
server, such as the address and port where it is listening to:

  <info>php %command.full_name%</info>

To get the information as a machine readable format, use the
<comment>--filter</> option:

<info>php %command.full_name% --filter=port</info>

Supported values are <comment>port</>, <comment>host</>, and <comment>address</>.
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
        $server = new WebServer($this->pidFileDirectory);
        if ($filter = $input->getOption('filter')) {
            if ($server->isRunning($input->getOption('pidfile'))) {
                list($host, $port) = explode(':', $address = $server->getAddress($input->getOption('pidfile')));
                if ('address' === $filter) {
                    $output->write($address);
                } elseif ('host' === $filter) {
                    $output->write($host);
                } elseif ('port' === $filter) {
                    $output->write($port);
                } else {
                    throw new InvalidArgumentException(sprintf('"%s" is not a valid filter.', $filter));
                }
            } else {
                return 1;
            }
        } else {
            if ($server->isRunning($input->getOption('pidfile'))) {
                $io->success(sprintf('Web server still listening on http://%s', $server->getAddress($input->getOption('pidfile'))));
            } else {
                $io->warning('No web server is listening.');

                return 1;
            }
        }

        return 0;
    }
}
