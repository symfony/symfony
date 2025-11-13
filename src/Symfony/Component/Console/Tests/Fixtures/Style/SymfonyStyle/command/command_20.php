<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure that closing tag is applied once
return function (InputInterface $input, OutputInterface $output): int {
    $output->setDecorated(true);
    $output = new SymfonyStyle($input, $output);
    $output->write('<question>do you want <comment>something</>');
    $output->writeln('?</>');

    return 0;
};
