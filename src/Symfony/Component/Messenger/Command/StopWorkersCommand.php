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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\WorkerRestarter;

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
                new InputArgument('receivers', InputArgument::IS_ARRAY, 'Names of the receivers/transports whose workers to stop, all workers when omitted'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command sends a signal to stop any <info>messenger:consume</info> processes that are running.

                    <info>php %command.full_name%</info>

                Pass the names of some receivers/transports to only stop the workers that consume them:

                    <info>php %command.full_name% scheduler_default</info>

                Each worker command will finish the message they are currently processing
                and then exit. Worker commands are *not* automatically restarted: that
                should be handled by a process control system.
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $receivers = $input->getArgument('receivers');
        new WorkerRestarter($this->restartSignalCachePool)(...$receivers);

        $io->success($receivers ? \sprintf('Signal successfully sent to stop the workers that consume "%s".', implode('", "', $receivers)) : 'Signal successfully sent to stop any running workers.');

        return 0;
    }
}
