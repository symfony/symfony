<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Ensure texts with emojis don't make longer lines than expected
return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);
    $output->success('Lorem ipsum dolor sit amet');
    $output->success('Lorem ipsum dolor sit amet with one emoji 🎉');
    $output->success('Lorem ipsum dolor sit amet with so many of them 👩‍🌾👩‍🌾👩‍🌾👩‍🌾👩‍🌾');

    return 0;
};
