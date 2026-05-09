<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure outlineBlock() wraps long messages and separates multiple messages with blank lines
return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);
    $output->outlineBlock(['First message.', 'Second message.', 'Third message.'], 'Build', 'fg=blue');
    $output->outlineSuccess('This is a very long message that should wrap across multiple lines because it exceeds the maximum line length of the terminal.');

    return 0;
};
