<?php

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\SingleCommandApplication;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new class extends SingleCommandApplication {})
    ->setDefinition(new InputDefinition([
        new InputOption('choice', null, InputOption::VALUE_NONE, ''),
        new InputOption('hidden', null, InputOption::VALUE_NONE, ''),
    ]))
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        if ($input->getOption('choice')) {
            $this->getHelper('question')
                 ->ask($input, $output, new ChoiceQuestion('😊', ['n']));
        } else {
            $question = new Question('😊');
            $question->setHidden(true);
            $this->getHelper('question')
                 ->ask($input, $output, $question);
        }

        return 0;
    })
    ->run()

;
