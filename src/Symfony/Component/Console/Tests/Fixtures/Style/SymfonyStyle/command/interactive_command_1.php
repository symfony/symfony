<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Style\SymfonyStyleWithForcedLineLength;

//Ensure that questions have the expected outputs
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyleWithForcedLineLength($input, $output);
    $questions = array(
        'What\'s your name?',
        'How are you?',
        'Where do you come from?',
    );
    $inputs = array('Foo', 'Bar', 'Baz');
    $stream = fopen('php://memory', 'r+', false);

    fputs($stream, implode(PHP_EOL, $inputs));
    rewind($stream);

    $output->setInputStream($stream);
    $output->ask($questions[0]);
    $output->ask($questions[1]);
    $output->ask($questions[2]);
};
