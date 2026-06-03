<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask(expression: '* * * * *', schedule: 'dummy_command')]
#[AsCronTask(expression: '0 * * * *', arguments: 'test', schedule: 'dummy_command')]
#[AsCommand(name: 'test:dummy-command')]
class DummyCommand extends Command
{
    public static array $calls = [];

    public function configure(): void
    {
        $this->addArgument('dummy-argument', InputArgument::OPTIONAL);
    }

    public function execute(InputInterface $input, ?OutputInterface $output = null): int
    {
        self::$calls[__FUNCTION__][] = $input->getArgument('dummy-argument');

        return Command::SUCCESS;
    }
}
