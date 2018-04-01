<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure that questions have the expected outputs
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);
    $stream = fopen('php://memory', 'r+', false);

    fwrite($stream, "Foo\nBar\nBaz");
    rewind($stream);
    $input->setStream($stream);

    $output->ask('What\'s your name?');
    $output->ask('How are you?');
    $output->ask('Where do you come from?');
};
