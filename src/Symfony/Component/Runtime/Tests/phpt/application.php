<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/autoload.php';

return function (array $context) {
    $command = new Command('go');
    $command->setCode(function (InputInterface $input, OutputInterface $output) use ($context) {
        $output->write('OK Application '.$context['SOME_VAR']);
    });

    $app = new Application();
    $app->add($command);
    $app->setDefaultCommand('go', true);

    return $app;
};
