<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$application = new Application();
$application->addCommand((new Command('test:command'))
    ->addOption('exit', null, InputOption::VALUE_REQUIRED, '', 0)
    ->addOption('sleep', null, InputOption::VALUE_REQUIRED, '', 0)
    ->addOption('stderr')
    ->setCode(static function (InputInterface $input, OutputInterface $output): int {
        $output->write('some message');

        if ($input->getOption('stderr') && $output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->write('some error');
        }

        if ($sleep = (int) $input->getOption('sleep')) {
            sleep($sleep);
        }

        return (int) $input->getOption('exit');
    })
);

$application->run();
