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
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * @author Payene Denis Kombate <denis.kombate@gmail.com>
 */
#[AsCommand(name: 'messenger:show', description: 'Show one or more pending messages from a transport')]
class ShowMessagesCommand extends Command
{
    public function __construct(
        private ContainerInterface $receiverLocator,
        private array $receiverNames = [],
        private ?PhpSerializer $phpSerializer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('transport', InputArgument::OPTIONAL, 'Name of the transport to inspect'),
                new InputArgument('id', InputArgument::OPTIONAL, 'Specific message id to show'),
                new InputOption('max', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to list', 50),
                new InputOption('stats', null, InputOption::VALUE_NONE, 'Display the message count by class'),
                new InputOption('class-filter', null, InputOption::VALUE_REQUIRED, 'Filter by a specific class name'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command shows messages that are pending in a transport:

                    <info>php %command.full_name% <transport></info>

                Or look at a specific message by its id:

                    <info>php %command.full_name% <transport> {id}</info>

                The listing can be narrowed down to a specific message class:

                    <info>php %command.full_name% <transport> --class-filter='App\Message\SendEmail'</info>

                Use the <info>--stats</info> option to display the message count by class instead:

                    <info>php %command.full_name% <transport> --stats</info>
                EOF
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($this->receiverNames && !$input->getArgument('transport')) {
            $io = new SymfonyStyle($input, $output);
            $question = new ChoiceQuestion('Which transport do you want to inspect?', $this->receiverNames, 0);
            $input->setArgument('transport', $io->getErrorStyle()->askQuestion($question));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $id = $input->getArgument('id');
        $classFilter = $input->getOption('class-filter');

        if (null !== $id && (null !== $classFilter || $input->getOption('stats'))) {
            throw new RuntimeException('You cannot specify a message id when using the "--stats" or "--class-filter" options.');
        }

        if (!$transportName = $input->getArgument('transport')) {
            throw new RuntimeException('Please pass the name of the transport to inspect.');
        }

        if (!$this->receiverLocator->has($transportName)) {
            $message = \sprintf('The "%s" transport does not exist.', $transportName);
            if ($this->receiverNames) {
                $message .= \sprintf(' Valid transports are: %s.', implode(', ', $this->receiverNames));
            }

            throw new RuntimeException($message);
        }

        $receiver = $this->receiverLocator->get($transportName);

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(\sprintf('The "%s" transport does not support listing or showing specific messages.', $transportName));
        }

        if ($input->getOption('stats')) {
            $max = $input->hasParameterOption(['--max'], true) ? $input->getOption('max') : null;
            $this->listMessagesPerClass($receiver, $io, $max, $classFilter);
        } elseif (null === $id) {
            $this->listMessages($receiver, $transportName, $io, $errorIo, $input->getOption('max'), $classFilter);
        } else {
            $this->showMessage($receiver, $id, $io, $errorIo);
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('transport')) {
            $suggestions->suggestValues($this->receiverNames);
        }
    }

    private function listMessages(ListableReceiverInterface $receiver, string $transportName, SymfonyStyle $io, SymfonyStyle $errorIo, int $max, ?string $classFilter): void
    {
        $envelopes = $receiver->all($max);

        $rows = [];

        if ($classFilter) {
            $errorIo->comment(\sprintf('Displaying only \'%s\' messages', $classFilter));
        }

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($envelopes as $envelope) {
                $currentClassName = $envelope->getMessage()::class;

                if ($classFilter && $classFilter !== $currentClassName) {
                    continue;
                }

                $rows[] = [$this->getMessageId($envelope), $currentClassName];
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }

        $rowsCount = \count($rows);

        if (0 === $rowsCount) {
            $io->success('No pending messages were found.');

            return;
        }

        $io->table(['Id', 'Class'], $rows);

        if ($rowsCount === $max) {
            $errorIo->comment(\sprintf('Showing first %d messages.', $max));
        } elseif ($classFilter) {
            $errorIo->comment(\sprintf('Showing %d message(s).', $rowsCount));
        }

        $errorIo->comment(\sprintf('Run <comment>messenger:show %s {id} -vv</comment> to see message details.', $transportName));
    }

    private function listMessagesPerClass(ListableReceiverInterface $receiver, SymfonyStyle $io, ?int $max, ?string $classFilter): void
    {
        $envelopes = $receiver->all($max);

        $countPerClass = [];

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($envelopes as $envelope) {
                $c = $envelope->getMessage()::class;

                if ($classFilter && $classFilter !== $c) {
                    continue;
                }

                if (!isset($countPerClass[$c])) {
                    $countPerClass[$c] = [$c, 0];
                }

                ++$countPerClass[$c][1];
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }

        if (!$countPerClass) {
            $io->success('No pending messages were found.');

            return;
        }

        $io->table(['Class', 'Count'], $countPerClass);
    }

    private function showMessage(ListableReceiverInterface $receiver, string $id, SymfonyStyle $io, SymfonyStyle $errorIo): void
    {
        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            $envelope = $receiver->find($id);
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }
        if (null === $envelope) {
            throw new RuntimeException(\sprintf('The message "%s" was not found.', $id));
        }

        $io->title('Message Details');

        $messageClass = $envelope->getMessage()::class;
        $lastMessageDecodingFailed = MessageDecodingFailedException::class === $messageClass || $envelope->last(MessageDecodingFailedStamp::class);

        $rows = [
            ['Class', $messageClass],
        ];

        if (null !== $id = $this->getMessageId($envelope)) {
            $rows[] = ['Message Id', $id];
        }

        $io->table([], $rows);

        if ($io->isVeryVerbose()) {
            $io->title('Message:');
            if ($lastMessageDecodingFailed) {
                $errorIo->error('The message could not be decoded. See below an APPROXIMATIVE representation of the class.');
            }
            $dump = new Dumper($io);
            $io->writeln($dump($envelope->getMessage()));
        } else {
            if ($lastMessageDecodingFailed) {
                $errorIo->error('The message could not be decoded.');
            }
            $io->writeln(' Re-run command with <info>-vv</info> to see more message details.');
        }
    }

    private function getMessageId(Envelope $envelope): mixed
    {
        return $envelope->last(TransportMessageIdStamp::class)?->getId();
    }
}
