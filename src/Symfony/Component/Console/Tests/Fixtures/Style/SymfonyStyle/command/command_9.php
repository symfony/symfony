<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Style\SymfonyStyleWithForcedLineLength;

//Ensure progress bar redraw frequency is updated
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyleWithForcedLineLength($input, $output);
    $output->progressStart(4);
    $output->progressRedrawFrequency(2);
    $output->progressAdvance(1);
    $output->progressAdvance(1);
    $output->progressAdvance(1);
    $output->progressAdvance(1);
    $output->progressFinish();
};
