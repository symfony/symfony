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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'messenger:stop-workers', description: 'Stop workers after their current message')]
class StopWorkersCommand extends Command
{
    public function __construct(
        private CacheItemPoolInterface $restartSignalCachePool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputOption('duration', 'd', InputOption::VALUE_REQUIRED, 'Duration in seconds during which workers are paused (not processing messages)'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command sends a signal to stop any <info>messenger:consume</info> processes that are running.

                    <info>php %command.full_name%</info>

                Each worker command will finish the message they are currently processing
                and then exit. Worker commands are *not* automatically restarted: that
                should be handled by a process control system.

                Use the <info>--duration</info> option to keep the workers in a paused state (not processing messages) for the given duration (in seconds).
                During this time, no messages will be handled, and the workers will not resume until the pause period has passed:

                    <info>php %command.full_name% --duration=60</info>
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $duration = (int) $input->getOption('duration');

        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true) + $duration);
        $this->restartSignalCachePool->save($cacheItem);

        $io->success('Signal successfully sent to stop any running workers.');
        if ($duration > 0) {
            $io->info(\sprintf('Workers will be paused for %s seconds.', $duration));
        }

        return 0;
    }
}
