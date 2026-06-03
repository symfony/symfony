--TEST--
Single Application can be executed
--FILE--
<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new SingleCommandApplication())
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $output->writeln('Hello World!');

        return 0;
    })
    ->run()
;
?>
--EXPECT--
Hello World!
