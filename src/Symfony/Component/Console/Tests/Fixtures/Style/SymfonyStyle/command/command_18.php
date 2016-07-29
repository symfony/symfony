<?php

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ensure that all lines are aligned to the begin of the first one and start with '//' in a very long line comment
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyle($input, $output);

    try {
        $output->progressFinish();
    } catch (RuntimeException $e) {
        $output->writeln('OK');

        return;
    }

    $output->writeln('KO');
};
