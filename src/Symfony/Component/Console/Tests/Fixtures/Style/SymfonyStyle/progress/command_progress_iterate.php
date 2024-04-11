<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// progressIterate
return function (InputInterface $input, OutputInterface $output) {
    $style = new SymfonyStyle($input, $output);

    foreach ($style->progressIterate(\range(1, 10)) as $step) {
        // noop
    }

    $style->writeln('end of progressbar');
};
