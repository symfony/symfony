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
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
#[AsCommand(name: 'messenger:setup-transports', description: 'Prepare the required infrastructure for the transport')]
class SetupTransportsCommand extends Command
{
    public function __construct(
        private ContainerInterface $transportLocator,
        private array $transportNames = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('transport', InputArgument::OPTIONAL, 'Name of the transport to setup', null)
            ->setHelp(<<<EOF
                The <info>%command.name%</info> command setups the transports:

                    <info>php %command.full_name%</info>

                Or a specific transport only:

                    <info>php %command.full_name% <transport></info>
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $transportNames = $this->transportNames;
        // do we want to set up only one transport?
        if ($transport = $input->getArgument('transport')) {
            if (!$this->transportLocator->has($transport)) {
                throw new \RuntimeException(\sprintf('The "%s" transport does not exist.', $transport));
            }
            $transportNames = [$transport];
        }

        foreach ($transportNames as $id => $transportName) {
            $transport = $this->transportLocator->get($transportName);
            if (!$transport instanceof SetupableTransportInterface) {
                $io->note(\sprintf('The "%s" transport does not support setup.', $transportName));
                continue;
            }

            try {
                $transport->setup();
                $io->success(\sprintf('The "%s" transport was set up successfully.', $transportName));
            } catch (\Exception $e) {
                throw new \RuntimeException(\sprintf('An error occurred while setting up the "%s" transport: ', $transportName).$e->getMessage(), 0, $e);
            }
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('transport')) {
            $suggestions->suggestValues($this->transportNames);
        }
    }
}
