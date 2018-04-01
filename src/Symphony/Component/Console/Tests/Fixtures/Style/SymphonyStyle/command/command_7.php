<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure questions do not output anything when input is non-interactive
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);
    $output->title('Title');
    $output->askHidden('Hidden question');
    $output->choice('Choice question with default', array('choice1', 'choice2'), 'choice1');
    $output->confirm('Confirmation with yes default', true);
    $output->text('Duis aute irure dolor in reprehenderit in voluptate velit esse');
};
