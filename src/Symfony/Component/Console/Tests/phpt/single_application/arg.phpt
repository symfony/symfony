--TEST--
Single Application can be executed
--ARGS--
You
--FILE--
<?php

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new SingleCommandApplication())
    ->addArgument('who', InputArgument::OPTIONAL, 'Who', 'World')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $output->writeln(sprintf('Hello %s!', $input->getArgument('who')));

        return 0;
    })
    ->run()
;
?>
--EXPECT--
Hello You!
