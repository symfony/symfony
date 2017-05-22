<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerListCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('worker:list')
            ->setDescription('List available workers.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers = $this->getContainer()->getParameter('worker.workers');

        foreach ($workers as $name => $_) {
            $output->writeln($name);
        }
    }
}
