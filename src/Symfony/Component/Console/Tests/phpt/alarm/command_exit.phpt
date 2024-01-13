--TEST--
Test command that exits
--SKIPIF--
<?php if (!extension_loaded("pcntl")) die("Skipped: pcntl extension required."); ?>
--FILE--
<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\AlarmableCommandInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

class MyCommand extends Command implements AlarmableCommandInterface
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        sleep(5);

        $output->writeln('should not be displayed');

        return 0;
    }

    public function getAlarmInterval(InputInterface $input): int
    {
        return 1;
    }

    public function handleAlarm(int|false $previousExitCode = 0): int|false
    {
        echo "Received alarm!";

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
Received alarm!
