<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Style\SymfonyStyleWithForcedLineLength;

//Ensure progress bar is well displayed
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyleWithForcedLineLength($input, $output);
    $output->progressStart(3);
    $output->progressRedrawFrequency(1);
    $output->progressAdvance(1);
    $output->progressAdvance(1);
    $output->progressAdvance(1);
    $output->progressFinish();
};
