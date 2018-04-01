<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure has proper blank line after text block when using a block like with SymphonyStyle::success
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);

    $output->listing(array(
        'Lorem ipsum dolor sit amet',
        'consectetur adipiscing elit',
    ));
    $output->success('Lorem ipsum dolor sit amet');
};
