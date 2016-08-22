<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\RepeatedQuestion;

//Ensure that questions have the expected outputs
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyle($input, $output);
    $stream = fopen('php://memory', 'r+', false);

    fputs($stream, "Foo\nBar\nBaz\nAwesome!\nThat's a nice place\n\n");
    rewind($stream);
    $input->setStream($stream);

    $output->ask('What\'s your name?');
    $output->ask('How are you?');
    $output->ask('Where do you come from?');

    $question = new RepeatedQuestion('Do you have any comment?', call_user_func(function () {
        do {
            $answer = (yield 'default');
        } while ('default' !== $answer);
    }));
    $output->askQuestion($question);
};
