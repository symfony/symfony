<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

//Ensure symphony style helper methods handle trailing backslashes properly when decorating user texts
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymphonyStyle($input, $output);

    $output->title('Title ending with \\');
    $output->section('Section ending with \\');
};
