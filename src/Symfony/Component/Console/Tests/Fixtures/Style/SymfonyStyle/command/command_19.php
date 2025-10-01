<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Ensure formatting tables when using multiple headers with TableCell
return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);
    $output->horizontalTable(['a', 'b', 'c', 'd'], [[1, 2, 3], [4, 5], [7, 8, 9]]);

    return 0;
};
