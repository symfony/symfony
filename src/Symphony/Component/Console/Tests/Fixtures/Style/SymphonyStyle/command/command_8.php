<?php

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\Console\Helper\TableCell;

//Ensure formatting tables when using multiple headers with TableCell
return function (InputInterface $input, OutputInterface $output) {
    $headers = array(
        array(new TableCell('Main table title', array('colspan' => 3))),
        array('ISBN', 'Title', 'Author'),
    );

    $rows = array(
        array(
            '978-0521567817',
            'De Monarchia',
            new TableCell("Dante Alighieri\nspans multiple rows", array('rowspan' => 2)),
        ),
        array('978-0804169127', 'Divine Comedy'),
    );

    $output = new SymphonyStyle($input, $output);
    $output->table($headers, $rows);
};
