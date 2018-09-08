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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMemoryUsageIsExceededReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMessageCountIsExceededReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenTimeLimitIsReachedReceiver;
use Symfony\Component\Messenger\Worker;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ConsumeMessagesCommand extends Command
{
    protected static $defaultName = 'messenger:consume-messages';

    private $busLocator;
    private $receiverLocator;
    private $logger;
    private $receiverNames;
    private $busNames;

    public function __construct(ContainerInterface $busLocator, ContainerInterface $receiverLocator, LoggerInterface $logger = null, array $receiverNames = array(), array $busNames = array())
    {
        $this->busLocator = $busLocator;
        $this->receiverLocator = $receiverLocator;
        $this->logger = $logger;
        $this->receiverNames = $receiverNames;
        $this->busNames = $busNames;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $defaultReceiverName = 1 === \count($this->receiverNames) ? current($this->receiverNames) : null;
        $defaultBusName = 1 === \count($this->busNames) ? current($this->busNames) : null;

        $this
            ->setDefinition(array(
                new InputArgument('receiver', $defaultReceiverName ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'Name of the receiver', $defaultReceiverName),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of received messages'),
                new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run'),
                new InputOption('bus', 'b', InputOption::VALUE_REQUIRED, 'Name of the bus to which received messages should be dispatched', $defaultBusName),
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
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);

        if ($this->receiverNames && !$this->receiverLocator->has($receiverName = $input->getArgument('receiver'))) {
            if (null === $receiverName) {
                $style->block('Missing receiver argument.', null, 'error', ' ', true);
                $input->setArgument('receiver', $style->choice('Select one of the available receivers', $this->receiverNames));
            } elseif ($alternatives = $this->findAlternatives($receiverName, $this->receiverNames)) {
                $style->block(sprintf('Receiver "%s" is not defined.', $receiverName), null, 'error', ' ', true);
                if ($style->confirm(sprintf('Do you want to receive from "%s" instead? ', $alternatives[0]), false)) {
                    $input->setArgument('receiver', $alternatives[0]);
                }
            }
        }

        $busName = $input->getOption('bus');
        if ($this->busNames && !$this->busLocator->has($busName)) {
            if (null === $busName) {
                $style->block('Missing bus argument.', null, 'error', ' ', true);
                $input->setOption('bus', $style->choice('Select one of the available buses', $this->busNames));
            } elseif ($alternatives = $this->findAlternatives($busName, $this->busNames)) {
                $style->block(sprintf('Bus "%s" is not defined.', $busName), null, 'error', ' ', true);

                if (1 === \count($alternatives)) {
                    if ($style->confirm(sprintf('Do you want to dispatch to "%s" instead? ', $alternatives[0]), true)) {
                        $input->setOption('bus', $alternatives[0]);
                    }
                } else {
                    $input->setOption('bus', $style->choice('Did you mean one of the following buses instead?', $alternatives, $alternatives[0]));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->receiverLocator->has($receiverName = $input->getArgument('receiver'))) {
            throw new RuntimeException(sprintf('Receiver "%s" does not exist.', $receiverName));
        }

        if (!$this->busLocator->has($busName = $input->getOption('bus'))) {
            throw new RuntimeException(sprintf('Bus "%s" does not exist.', $busName));
        }

        $receiver = $this->receiverLocator->get($receiverName);
        $bus = $this->busLocator->get($busName);

        if ($limit = $input->getOption('limit')) {
            $receiver = new StopWhenMessageCountIsExceededReceiver($receiver, $limit, $this->logger);
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $receiver = new StopWhenMemoryUsageIsExceededReceiver($receiver, $this->convertToBytes($memoryLimit), $this->logger);
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $receiver = new StopWhenTimeLimitIsReachedReceiver($receiver, $timeLimit, $this->logger);
        }

        $worker = new Worker($receiver, $bus);
        $worker->run();
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

    private function findAlternatives($name, array $collection)
    {
        $alternatives = array();
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
