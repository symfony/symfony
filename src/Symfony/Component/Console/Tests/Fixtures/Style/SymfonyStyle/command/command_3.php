<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Ensure has single blank line between two titles
return function (InputInterface $input, OutputInterface $output): void {
    $output = new SymfonyStyle($input, $output);
    $output->title('First title');
    $output->title('Second title');
};
