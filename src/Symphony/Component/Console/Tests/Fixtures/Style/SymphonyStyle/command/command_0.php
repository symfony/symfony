<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure has single blank line at start when using block element
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);
    $output->caution('Lorem ipsum dolor sit amet');
};
