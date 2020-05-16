<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class SetupTransportsCommand extends Command
{
    protected static $defaultName = 'messenger:setup-transports';

    private $transportLocator;
    private $transportNames;

    public function __construct(ContainerInterface $transportLocator, array $transportNames = [])
    {
        $this->transportLocator = $transportLocator;
        $this->transportNames = $transportNames;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument('transport', InputArgument::OPTIONAL, 'Name of the transport to setup', null)
            ->setDescription('Prepares the required infrastructure for the transport')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command setups the transports:

    <info>php %command.full_name%</info>

Or a specific transport only:

    <info>php %command.full_name% <transport></info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $transportNames = $this->transportNames;
        // do we want to set up only one transport?
        if ($transport = $input->getArgument('transport')) {
            if (!$this->transportLocator->has($transport)) {
                throw new \RuntimeException(sprintf('The "%s" transport does not exist.', $transport));
            }
            $transportNames = [$transport];
        }

        foreach ($transportNames as $id => $transportName) {
            $transport = $this->transportLocator->get($transportName);
            if ($transport instanceof SetupableTransportInterface) {
                $transport->setup();
                $io->success(sprintf('The "%s" transport was set up successfully.', $transportName));
            } else {
                $io->note(sprintf('The "%s" transport does not support setup.', $transportName));
            }
        }

        return 0;
    }
}
