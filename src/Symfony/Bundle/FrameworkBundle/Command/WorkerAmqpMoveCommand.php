<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerAmqpMoveCommand extends ContainerAwareCommand
{
    private $broker;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('worker:amqp:move')
            ->setDescription('Take all messages from a queue, and send them to the default exchange with a new routing key.')
            ->setDefinition(array(
                new InputArgument('from', InputArgument::REQUIRED, 'The queue.'),
                new InputArgument('to', InputArgument::REQUIRED, 'The new routing key.'),
            ))
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->broker = $this->getContainer()->get('amqp.broker');
        $this->logger = $this->getContainer()->get('logger');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        while (false !== $message = $this->broker->get($from)) {
            $this->logger->info('Move a message from {from} to {to}.', array(
                'from' => $from,
                'to' => $to,
            ));
            $this->broker->move($message, $to);
            $this->broker->ack($message);
            $this->logger->debug('...message moved {from} to {to}.', array(
                'from' => $from,
                'to' => $to,
            ));
        }
    }
}
