<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/autoload.php';

return function (Command $command, InputInterface $input, OutputInterface $output, array $context) {
    $command->addOption('hello', 'e', InputOption::VALUE_REQUIRED, 'How should I greet?', 'OK');

    return $command->setCode(function () use ($input, $output, $context) {
        $output->write($input->getOption('hello').' Command '.$context['SOME_VAR']);
    });
};
