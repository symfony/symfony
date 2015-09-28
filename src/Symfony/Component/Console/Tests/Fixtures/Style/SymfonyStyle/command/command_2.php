<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Style\SymfonyStyleWithForcedLineLength;

//Ensure has single blank line between blocks
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyleWithForcedLineLength($input, $output);
    $output->warning('Warning');
    $output->caution('Caution');
    $output->error('Error');
    $output->success('Success');
    $output->note('Note');
    $output->block('Custom block', 'CUSTOM', 'fg=white;bg=green', 'X ', true);
};
