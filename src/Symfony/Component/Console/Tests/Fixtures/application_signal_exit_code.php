<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$application = new Application();
$application->setDispatcher(new EventDispatcher());
$application->addCommand(new class(\constant($argv[1])) extends Command {
    public function __construct(
        private int $signal,
    ) {
        parent::__construct('signal');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        posix_kill(posix_getpid(), $this->signal);

        $output->writeln('should not be displayed');

        return 0;
    }
});
$application->setDefaultCommand('signal', true);
$application->run(new ArrayInput([]));
