<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/autoload.php';

return function (Command $command, InputInterface $input, OutputInterface $output, array $context) {
    $command->addArgument('name', null, 'Who should I greet?', 'World');

    return static function () use ($input, $output, $context) {
        $output->writeln(sprintf('Hello %s', $input->getArgument('name')));
        $output->write('OK Command '.$context['SOME_VAR']);
    };
};
