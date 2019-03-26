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
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\Worker\StopWhenMemoryUsageIsExceededWorker;
use Symfony\Component\Messenger\Worker\StopWhenMessageCountIsExceededWorker;
use Symfony\Component\Messenger\Worker\StopWhenTimeLimitIsReachedWorker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class ConsumeMessagesCommand extends Command
{
    protected static $defaultName = 'messenger:consume';

    private $busLocator;
    private $receiverLocator;
    private $logger;
    private $receiverNames;
    private $retryStrategyLocator;
    private $eventDispatcher;

    public function __construct(ContainerInterface $busLocator, ContainerInterface $receiverLocator, LoggerInterface $logger = null, array $receiverNames = [], /* ContainerInterface */ $retryStrategyLocator = null, EventDispatcherInterface $eventDispatcher = null)
    {
        if (\is_array($retryStrategyLocator)) {
            @trigger_error(sprintf('The 5th argument of the class "%s" should be a retry-strategy locator, an array of bus names as a value is deprecated since Symfony 4.3.', __CLASS__), E_USER_DEPRECATED);

            $retryStrategyLocator = null;
        }

        $this->busLocator = $busLocator;
        $this->receiverLocator = $receiverLocator;
        $this->logger = $logger;
        $this->receiverNames = $receiverNames;
        $this->retryStrategyLocator = $retryStrategyLocator;
        $this->eventDispatcher = $eventDispatcher;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $defaultReceiverName = 1 === \count($this->receiverNames) ? current($this->receiverNames) : null;

        $this
            ->setDefinition([
                new InputArgument('receivers', InputArgument::IS_ARRAY, 'Names of the receivers/transports to consume in order of priority', $defaultReceiverName ? [$defaultReceiverName] : []),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of received messages'),
                new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run'),
                new InputOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep before asking for new messages after no messages were found', 1),
                new InputOption('bus', 'b', InputOption::VALUE_REQUIRED, 'Name of the bus to which received messages should be dispatched (if not passed, bus is determined automatically.'),
            ])
            ->setDescription('Consumes messages')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes messages and dispatches them to the message bus.

    <info>php %command.full_name% <receiver-name></info>

To receive from multiple transports, pass each name:

    <info>php %command.full_name% receiver1 receiver2</info>

Use the --limit option to limit the number of messages received:

    <info>php %command.full_name% <receiver-name> --limit=10</info>

Use the --memory-limit option to stop the worker if it exceeds a given memory usage limit. You can use shorthand byte values [K, M or G]:

    <info>php %command.full_name% <receiver-name> --memory-limit=128M</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:

    <info>php %command.full_name% <receiver-name> --time-limit=3600</info>

Use the --bus option to specify the message bus to dispatch received messages
to instead of trying to determine it automatically. This is required if the
messages didn't originate from Messenger:

    <info>php %command.full_name% <receiver-name> --bus=event_bus</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if ($this->receiverNames && 0 === \count($input->getArgument('receivers'))) {
            $io->block('Which transports/receivers do you want to consume?', null, 'fg=white;bg=blue', ' ', true);

            $io->writeln('Choose which receivers you want to consume messages from in order of priority.');
            if (\count($this->receiverNames) > 1) {
                $io->writeln(sprintf('Hint: to consume from multiple, use a list of their names, e.g. <comment>%s</comment>', implode(', ', $this->receiverNames)));
            }

            $question = new ChoiceQuestion('Select receivers to consume:', $this->receiverNames, 0);
            $question->setMultiselect(true);

            $input->setArgument('receivers', $io->askQuestion($question));
        }

        if (0 === \count($input->getArgument('receivers'))) {
            throw new RuntimeException('Please pass at least one receiver.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (false !== strpos($input->getFirstArgument(), ':consume-')) {
            $message = 'The use of the "messenger:consume-messages" command is deprecated since version 4.3 and will be removed in 5.0. Use "messenger:consume" instead.';
            @trigger_error($message, E_USER_DEPRECATED);
            $output->writeln(sprintf('<comment>%s</comment>', $message));
        }

        $receivers = [];
        $retryStrategies = [];
        foreach ($receiverNames = $input->getArgument('receivers') as $receiverName) {
            if (!$this->receiverLocator->has($receiverName)) {
                $message = sprintf('The receiver "%s" does not exist.', $receiverName);
                if ($this->receiverNames) {
                    $message .= sprintf(' Valid receivers are: %s.', implode(', ', $this->receiverNames));
                }

                throw new RuntimeException($message);
            }

            if (null !== $this->retryStrategyLocator && !$this->retryStrategyLocator->has($receiverName)) {
                throw new RuntimeException(sprintf('Receiver "%s" does not have a configured retry strategy.', $receiverName));
            }

            $receivers[$receiverName] = $this->receiverLocator->get($receiverName);
            $retryStrategies[$receiverName] = null !== $this->retryStrategyLocator ? $this->retryStrategyLocator->get($receiverName) : null;
        }

        if (null !== $input->getOption('bus')) {
            $bus = $this->busLocator->get($input->getOption('bus'));
        } else {
            $bus = new RoutableMessageBus($this->busLocator);
        }

        $worker = new Worker($receivers, $bus, $retryStrategies, $this->eventDispatcher, $this->logger);
        $stopsWhen = [];
        if ($limit = $input->getOption('limit')) {
            $stopsWhen[] = "processed {$limit} messages";
            $worker = new StopWhenMessageCountIsExceededWorker($worker, $limit, $this->logger);
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $stopsWhen[] = "exceeded {$memoryLimit} of memory";
            $worker = new StopWhenMemoryUsageIsExceededWorker($worker, $this->convertToBytes($memoryLimit), $this->logger);
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $stopsWhen[] = "been running for {$timeLimit}s";
            $worker = new StopWhenTimeLimitIsReachedWorker($worker, $timeLimit, $this->logger);
        }

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $io->success(sprintf('Consuming messages from transport%s "%s".', \count($receivers) > 0 ? 's' : '', implode(', ', $receiverNames)));

        if ($stopsWhen) {
            $last = array_pop($stopsWhen);
            $stopsWhen = ($stopsWhen ? implode(', ', $stopsWhen).' or ' : '').$last;
            $io->comment("The worker will automatically exit once it has {$stopsWhen}.");
        }

        $io->comment('Quit the worker with CONTROL-C.');

        if (OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
            $io->comment('Re-run the command with a -vv option to see logs about consumed messages.');
        }

        $worker->run([
            'sleep' => $input->getOption('sleep') * 1000000,
        ]);
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr(rtrim($memoryLimit, 'b'), -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

    private function findAlternatives($name, array $collection)
    {
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $threshold = 1e3;
        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) { return $lev < 2 * $threshold; });
        ksort($alternatives, SORT_NATURAL | SORT_FLAG_CASE);

        return array_keys($alternatives);
    }
}
