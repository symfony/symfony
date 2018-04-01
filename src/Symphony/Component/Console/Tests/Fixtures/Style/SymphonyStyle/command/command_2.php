<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure has single blank line between blocks
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);
    $output->warning('Warning');
    $output->caution('Caution');
    $output->error('Error');
    $output->success('Success');
    $output->note('Note');
    $output->block('Custom block', 'CUSTOM', 'fg=white;bg=green', 'X ', true);
};
