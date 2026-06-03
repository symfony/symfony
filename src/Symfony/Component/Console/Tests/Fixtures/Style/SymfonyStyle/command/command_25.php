<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure outlineBlock() renders correctly with a custom title and without a title
return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);
    $output->outlineBlock('Deployment finished in 3.2s', 'Deploy', 'fg=cyan');
    $output->outlineBlock('No title, just a plain bordered box.');

    return 0;
};
