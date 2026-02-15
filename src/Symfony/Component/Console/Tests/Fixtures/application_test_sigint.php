<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new class extends Command {
    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::OPTIONAL, default: 'single');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getArgument('mode');

        $question = new Question('Enter text: ');
        $question->setMultiline($mode !== 'single');

        $helper = new QuestionHelper();

        pcntl_async_signals(true);
        pcntl_signal(\SIGALRM, function () {
            posix_kill(posix_getpid(), \SIGINT);
            pcntl_signal_dispatch();
        });
        pcntl_alarm(1);

        $helper->ask($input, $output, $question);

        return Command::SUCCESS;
    }
})
    ->run(new ArgvInput($argv), new ConsoleOutput())
;
