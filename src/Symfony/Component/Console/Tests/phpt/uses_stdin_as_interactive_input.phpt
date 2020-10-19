--STDIN--
Hello World
--FILE--
<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new Application())
    ->register('app')
    ->setCode(function(InputInterface $input, OutputInterface $output) {
        $output->writeln((new QuestionHelper())->ask($input, $output, new Question('Foo?', 'foo')));
        $output->writeln((new QuestionHelper())->ask($input, $output, new Question('Bar?', 'bar')));
    })
    ->getApplication()
    ->setDefaultCommand('app', true)
    ->run()
;
--EXPECT--
Foo?Hello World
Bar?bar
