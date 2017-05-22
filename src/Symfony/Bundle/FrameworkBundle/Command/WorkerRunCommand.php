<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Amqp\Worker\ConfigurableLoopInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerRunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('worker:run')
            ->setDescription('Run a worker')
            ->setDefinition(array(
                new InputArgument('worker', InputArgument::REQUIRED, 'The worker'),
                new InputOption('name', null, InputOption::VALUE_REQUIRED, 'A name, useful for stats/monitoring. Defaults to worker name.'),
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('The pcntl extension is mandatory.');
        }

        $loop = $this->getLoop($input);

        $loopName = $input->getOption('name') ?: $loop->getName();

        if ($loop instanceof ConfigurableLoopInterface) {
            $loop->setName($loopName);
        }

        $processName = sprintf('%s_%s', $this->getContainer()->getParameter('worker.cli_title_prefix'), $loopName);

        // On OSX, it may raise an error:
        // Warning: cli_set_process_title(): cli_set_process_title had an error: Not initialized correctly
        @cli_set_process_title($processName);

        pcntl_signal(SIGTERM, function () use ($loop) {
            $loop->stop('Signaled with SIGTERM.');
        });
        pcntl_signal(SIGINT, function () use ($loop) {
            $loop->stop('Signaled with SIGINT.');
        });

        $loop->run();
    }

    private function getLoop(InputInterface $input)
    {
        $workers = $this->getContainer()->getParameter('worker.workers');

        $workerName = $input->getArgument('worker');

        if (!array_key_exists($workerName, $workers)) {
            throw new \InvalidArgumentException(sprintf(
                'The worker "%s" does not exist. Available ones are: "%s".',
                $workerName, implode('", "', array_keys($workers))
            ));
        }

        return $this->getContainer()->get($workers[$workerName]);
    }
}
