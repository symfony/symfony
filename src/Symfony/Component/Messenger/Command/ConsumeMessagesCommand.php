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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMemoryUsageIsExceededReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMessageCountIsExceededReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenTimeLimitIsReachedReceiver;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
class ConsumeMessagesCommand extends Command
{
    protected static $defaultName = 'messenger:consume-messages';

    private $bus;
    private $receiverLocator;
    private $logger;
    private $defaultReceiverName;

    public function __construct(MessageBusInterface $bus, ContainerInterface $receiverLocator, LoggerInterface $logger = null, string $defaultReceiverName = null)
    {
        parent::__construct();

        $this->bus = $bus;
        $this->receiverLocator = $receiverLocator;
        $this->logger = $logger;
        $this->defaultReceiverName = $defaultReceiverName;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition(array(
                new InputArgument('receiver', $this->defaultReceiverName ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'Name of the receiver', $this->defaultReceiverName),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of received messages'),
                new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run'),
            ))
            ->setDescription('Consumes messages')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes messages and dispatches them to the message bus.

    <info>php %command.full_name% <receiver-name></info>

Use the --limit option to limit the number of messages received:

    <info>php %command.full_name% <receiver-name> --limit=10</info>

Use the --memory-limit option to stop the worker if it exceeds a given memory usage limit. You can use shorthand byte values [K, M or G]:

    <info>php %command.full_name% <receiver-name> --memory-limit=128M</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:

    <info>php %command.full_name% <receiver-name> --time-limit=3600</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->receiverLocator->has($receiverName = $input->getArgument('receiver'))) {
            throw new \RuntimeException(sprintf('Receiver "%s" does not exist.', $receiverName));
        }

        if (!($receiver = $this->receiverLocator->get($receiverName)) instanceof ReceiverInterface) {
            throw new \RuntimeException(sprintf('Receiver "%s" is not a valid message consumer. It must implement the "%s" interface.', $receiverName, ReceiverInterface::class));
        }

        if ($limit = $input->getOption('limit')) {
            $receiver = new StopWhenMessageCountIsExceededReceiver($receiver, $limit, $this->logger);
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $receiver = new StopWhenMemoryUsageIsExceededReceiver($receiver, $this->convertToBytes($memoryLimit), $this->logger);
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $receiver = new StopWhenTimeLimitIsReachedReceiver($receiver, $timeLimit, $this->logger);
        }

        $worker = new Worker($receiver, $this->bus);
        $worker->run();
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($memoryLimit, -1)) {
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
}
