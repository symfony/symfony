<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\SingleCommandApplication;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new class extends SingleCommandApplication {
    public function getSubscribedSignals(): array
    {
        return [SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        exit(0);
    }
})
    ->setCode(function(InputInterface $input, OutputInterface $output): int {
        $this->getHelper('question')
             ->ask($input, $output, new ChoiceQuestion('😊', ['y']));

        return 0;
    })
    ->run()

;
