<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$dispatcher = new EventDispatcher();

$inner = new Application();
$inner->setAutoExit(false);
$inner->setDispatcher($dispatcher);
$inner->add((new Command('inner'))->setCode(static function (InputInterface $input, OutputInterface $output) {
    posix_kill(posix_getpid(), \SIGUSR1);
    $output->write('Inner done. ');

    return 0;
}));

$outer = new Application();
$outer->setDispatcher($dispatcher);
$outer->add(new class($inner) extends Command implements SignalableCommandInterface {
    private OutputInterface $output;

    public function __construct(private Application $inner)
    {
        parent::__construct('outer');
    }

    public function getSubscribedSignals(): array
    {
        return [\SIGUSR1];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->output->write('Outer handled the signal. ');

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->inner->run(new ArrayInput(['command' => 'inner']), $output);
        $output->write('Outer done.');

        return 0;
    }
});
$outer->run(new ArrayInput(['command' => 'outer']), new ConsoleOutput());
