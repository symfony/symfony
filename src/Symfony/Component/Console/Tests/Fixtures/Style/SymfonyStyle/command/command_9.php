<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Style\SymfonyStyleWithForcedLineLength;

//Ensure that all lines are aligned to the begin of the first line in a multi-line block
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyleWithForcedLineLength($input, $output);
    $output->block(array('Custom block', 'Second custom block line'), 'CUSTOM', 'fg=white;bg=green', 'X ', true);
};
