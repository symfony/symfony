<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ensure that nested tags have no effect on the color of the '//' prefix
return function (InputInterface $input, OutputInterface $output) {
    $output->setDecorated(true);
    $output = new SymfonyStyle($input, $output);
    $output->comment(
        sprintf(
            'Loading the configuration file "<comment>%s</comment>".',
            '/var/www/deploy/current/test/production/219099923320/feature-test-config/config/packages/alice.yml'
        )
    );
};
