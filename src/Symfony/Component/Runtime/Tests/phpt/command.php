<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/autoload.php';

return function (Command $command, InputInterface $input, OutputInterface $output, array $context) {
    $command->setCode(function () use ($output, $context) {
        $output->write('OK Command '.$context['SOME_VAR']);
    });

    return $command;
};
