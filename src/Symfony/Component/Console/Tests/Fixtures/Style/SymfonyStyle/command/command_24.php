<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure outline block styles render correctly with box-drawing borders
return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);
    $output->outlineSuccess('Lorem ipsum dolor sit amet');
    $output->outlineError('Lorem ipsum dolor sit amet');
    $output->outlineWarning('Lorem ipsum dolor sit amet');
    $output->outlineNote('Lorem ipsum dolor sit amet');
    $output->outlineInfo('Lorem ipsum dolor sit amet');
    $output->outlineCaution('Lorem ipsum dolor sit amet');

    return 0;
};
