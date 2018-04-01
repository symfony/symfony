<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure has proper line ending before outputting a text block like with SymphonyStyle::listing() or SymphonyStyle::text()
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);

    $output->writeln('Lorem ipsum dolor sit amet');
    $output->listing(array(
        'Lorem ipsum dolor sit amet',
        'consectetur adipiscing elit',
    ));

    //Even using write:
    $output->write('Lorem ipsum dolor sit amet');
    $output->listing(array(
        'Lorem ipsum dolor sit amet',
        'consectetur adipiscing elit',
    ));

    $output->write('Lorem ipsum dolor sit amet');
    $output->text(array(
        'Lorem ipsum dolor sit amet',
        'consectetur adipiscing elit',
    ));

    $output->newLine();

    $output->write('Lorem ipsum dolor sit amet');
    $output->comment(array(
        'Lorem ipsum dolor sit amet',
        'consectetur adipiscing elit',
    ));
};
