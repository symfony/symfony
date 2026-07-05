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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @author Payene Denis Kombate <denis.kombate@gmail.com>
 */
#[AsCommand( name: 'messenger:show', description: 'Affiche les messages en attente dans un transport spécifique.',)]
class ShowMessagesCommand extends Command
{
    // On injecte le locator qui contient TOUS les transports de l'application
    public function __construct(
        private ContainerInterface $receiverLocator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'The message ID to show')
            ->addOption('transport', 't', InputOption::VALUE_REQUIRED, 'The transport name to inspect')
            ->addOption('class-filter', null, InputOption::VALUE_REQUIRED, 'Filter by message class name')
            ->addOption('max', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to display', 50)
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Display only statistics about the queue');
    }



    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $transportName = $input->getOption('transport');

        if (!$transportName) {
            $io->error('The --transport option is required.');
            return Command::FAILURE;
        }

        if (!$this->receiverLocator->has($transportName)) {
            $io->error(sprintf('The transport "%s" does not exist.', $transportName));
            return Command::FAILURE;
        }

        $receiver = $this->receiverLocator->get($transportName);

        if (!$receiver instanceof ListableReceiverInterface) {
            $io->error(sprintf('The transport "%s" does not support browsing messages.', $transportName));
            return Command::FAILURE;
        }

        $id = $input->getArgument('id');
        $classFilter = $input->getOption('class-filter');

        // Cas 1 : Affichage d'un message unique par ID
        if (null !== $id) {
            $envelope = $receiver->find($id);
            if (null === $envelope) {
                $io->warning(sprintf('Message with ID "%s" not found in transport "%s".', $id, $transportName));
                return Command::SUCCESS;
            }
            
            $this->displaySingleMessage($io, $envelope, $id);
            return Command::SUCCESS;
        }

        // Cas 2 : Récupération d'une liste de messages
        $max = (int) $input->getOption('max');
        $envelopes = $receiver->all($max);

        if (empty($envelopes)) {
            $io->success(sprintf('No pending messages found in transport "%s".', $transportName));
            return Command::SUCCESS;
        }

        // Filtrage par classe si option spécifiée
        if ($classFilter) {
            $envelopes = array_filter($envelopes, function ($envelope) use ($classFilter) {
                return str_contains(get_class($envelope->getMessage()), $classFilter);
            });
        }

        // Cas 3 : Affichage simple des statistiques (--stats)
        if ($input->getOption('stats')) {
            $io->info(sprintf('Pending messages count listed (max %d): %d', $max, count($envelopes)));
            return Command::SUCCESS;
        }

        // Cas 4 : Rendu du tableau graphique standard
        $rows = [];
        foreach ($envelopes as $envelope) {
            // Recherche de l'ID via le TransportMessageIdStamp s'il existe
            $idStamp = $envelope->last(\Symfony\Component\Messenger\Stamp\TransportMessageIdStamp::class);
            $messageId = $idStamp ? $idStamp->getId() : 'N/A';
            
            $rows[] = [
                $messageId,
                get_class($envelope->getMessage()),
            ];
        }

        $io->table(['ID', 'Message Class'], $rows);
        return Command::SUCCESS;
    }

    private function displaySingleMessage(SymfonyStyle $io, Envelope $envelope, string $id): void
    {
        $io->title(sprintf('Message ID: %s', $id));
        $io->text(sprintf('**Class:** %s', get_class($envelope->getMessage())));
        
        // Dump propre du contenu du message
        $io->section('Message Content');
        if (class_exists(\Symfony\Component\VarDumper\Dumper\CliDumper::class)) {
            $io->writeln(dump($envelope->getMessage()));
        } else {
            $io->writeln(print_r($envelope->getMessage(), true));
        }
    }


}
