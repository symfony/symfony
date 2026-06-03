<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ensure that nested tags have no effect on the color of the '//' prefix
return function (InputInterface $input, OutputInterface $output): int {
    $output->setDecorated(true);
    $output = new SymfonyStyle($input, $output);
    $output->block(
        'Árvíztűrőtükörfúrógép Lorem ipsum dolor sit <comment>amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</comment> Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum',
        '★', // UTF-8 star!
        null,
        '<fg=default;bg=default> ║ </>', // UTF-8 double line!
        false,
        false
    );

    return 0;
};
