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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * @author Kévin Thérage <therage.kevin@gmail.com>
 */
#[AsCommand(name: 'messenger:stats', description: 'Show the message count for one or more transports')]
class StatsCommand extends Command
{
    private ContainerInterface $transportLocator;
    private array $transportNames;

    public function __construct(ContainerInterface $transportLocator, array $transportNames = [])
    {
        $this->transportLocator = $transportLocator;
        $this->transportNames = $transportNames;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('transport_names', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'List of transports\' names')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command counts the messages for all the transports:

    <info>php %command.full_name%</info>

Or specific transports only:

    <info>php %command.full_name% <transportNames></info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $transportNames = $this->transportNames;
        if ($input->getArgument('transport_names')) {
            $transportNames = $input->getArgument('transport_names');
        }

        $outputTable = [];
        $uncountableTransports = [];
        foreach ($transportNames as $transportName) {
            if (!$this->transportLocator->has($transportName)) {
                $io->warning(sprintf('The "%s" transport does not exist.', $transportName));

                continue;
            }
            $transport = $this->transportLocator->get($transportName);
            if (!$transport instanceof MessageCountAwareInterface) {
                $uncountableTransports[] = $transportName;

                continue;
            }
            $outputTable[] = [$transportName, $transport->getMessageCount()];
        }

        $io->table(['Transport', 'Count'], $outputTable);

        if ($uncountableTransports) {
            $io->note(sprintf('Unable to get message count for the following transports: "%s".', implode('", "', $uncountableTransports)));
        }

        return 0;
    }
}
