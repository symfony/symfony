<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

$output = new ConsoleOutput();
$output->write("HELLO\nWORLD", false, OutputInterface::OUTPUT_RAW);
