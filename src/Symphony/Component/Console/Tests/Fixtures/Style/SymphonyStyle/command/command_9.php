<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure that all lines are aligned to the begin of the first line in a multi-line block
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);
    $output->block(array('Custom block', 'Second custom block line'), 'CUSTOM', 'fg=white;bg=green', 'X ', true);
};
