<?php

use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

return function (InputInterface $input, OutputInterface $output): int {
    $output = new SymfonyStyle($input, $output);

    $output->definitionList(
        ['foo' => 'bar'],
        new TableSeparator(),
        'this is a title',
        new TableSeparator(),
        ['foo2' => 'bar2']
    );

    return 0;
};
