<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

// ensure that nested tags have no effect on the color of the '//' prefix
return function (InputInterface $input, OutputInterface $output) {
    $output->setDecorated(true);
    $output = new SymphonyStyle($input, $output);
    $output->comment(
        'Lorem ipsum dolor sit <comment>amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</comment> Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum'
    );
};
