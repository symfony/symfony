<?php

namespace Symfony\Component\Worker\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class WorkerListCommand extends Command
{
    private $workers;

    public function __construct(array $workers = array())
    {
        parent::__construct();

        $this->workers = $workers;
    }

    protected function configure()
    {
        $this
            ->setName('worker:list')
            ->setDescription('Lists available workers.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->workers) {
            $io->getErrorStyle()->error('There are no available workers.');

            return;
        }

        $io->getErrorStyle()->title('Available workers');
        $io->listing($this->workers);
    }
}
