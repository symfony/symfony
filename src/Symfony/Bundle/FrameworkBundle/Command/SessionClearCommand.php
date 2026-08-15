<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\ClearableSessionHandlerInterface;

/**
 * Command to clear all session data.
 *
 * @author Julien Robic <nayte91@gmail.com>
 */
#[AsCommand(name: 'session:clear', description: 'Clear all session data')]
final class SessionClearCommand extends Command
{
    public function __construct(
        private \SessionHandlerInterface $sessionHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command clears all session data from the storage.

                    %command.full_name%

                This will clear all active sessions. Use the <info>--force</info> option to skip confirmation:

                    %command.full_name% --force
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->sessionHandler instanceof ClearableSessionHandlerInterface) {
            $io->error(\sprintf('The session handler "%s" does not support clearing sessions. It must implement "%s".', get_debug_type($this->sessionHandler), ClearableSessionHandlerInterface::class));

            return Command::FAILURE;
        }

        if ($input->isInteractive() && !$input->getOption('force') && !$io->confirm('This will clear all active sessions, including e.g. carts of anonymous users. Do you want to continue?', false)) {
            return Command::SUCCESS;
        }

        try {
            $this->sessionHandler->clear();
        } catch (\LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('All sessions have been cleared.');

        return Command::SUCCESS;
    }
}
