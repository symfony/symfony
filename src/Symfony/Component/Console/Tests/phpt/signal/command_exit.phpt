--TEST--
Test command that exits
--SKIPIF--
<?php if (!extension_loaded("pcntl")) echo "Skipped: pcntl extension required."; ?>
--FILE--
<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

class MyCommand extends Command implements SignalableCommandInterface
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        posix_kill(posix_getpid(), \SIGINT);

        $output->writeln('should not be displayed');

        return 0;
    }


    public function getSubscribedSignals(): array
    {
        return [\SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        echo "Received signal!";

        return 0;
    }
}

$app = new Application();
$app->setDispatcher(new \Symfony\Component\EventDispatcher\EventDispatcher());
$app->add(new MyCommand('foo'));

$app
    ->setDefaultCommand('foo', true)
    ->run()
;
--EXPECT--
Received signal!
