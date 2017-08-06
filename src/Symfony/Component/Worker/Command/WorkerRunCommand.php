<?php

namespace Symfony\Component\Worker\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Worker\Loop\ConfigurableLoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class WorkerRunCommand extends Command
{
    private $container;
    private $processTitlePrefix;
    private $workers;

    /**
     * @param ContainerInterface $container          A PSR11 container from which to load workers by names
     * @param string             $processTitlePrefix
     * @param string[]           $workers
     */
    public function __construct(ContainerInterface $container, $processTitlePrefix, array $workers = array())
    {
        parent::__construct();

        $this->container = $container;
        $this->processTitlePrefix = $processTitlePrefix;
        $this->workers = $workers;
    }

    protected function configure()
    {
        $this
            ->setName('worker:run')
            ->setDescription('Runs a worker')
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

        $workerName = $input->getArgument('worker');
        $loop = $this->getLoop($workerName);

        $loopName = $input->getOption('name') ?: $loop->getName();

        if ($loop instanceof ConfigurableLoopInterface) {
            $loop->setName($loopName);
        }

        $this->setProcessTitle(sprintf('%s_%s', $this->processTitlePrefix, $loopName));

        pcntl_signal(SIGTERM, function () use ($loop) {
            $loop->stop('Signaled with SIGTERM.');
        });
        pcntl_signal(SIGINT, function () use ($loop) {
            $loop->stop('Signaled with SIGINT.');
        });

        (new SymfonyStyle($input, $output))->success("Running worker $workerName");

        $loop->run();
    }

    private function getLoop($workerName)
    {
        if (!array_key_exists($workerName, $this->workers)) {
            throw new \InvalidArgumentException(sprintf(
                'The worker "%s" does not exist. Available ones are: "%s".',
                $workerName, implode('", "', $this->workers)
            ));
        }

        return $this->container->get($this->workers[$workerName]);
    }
}
