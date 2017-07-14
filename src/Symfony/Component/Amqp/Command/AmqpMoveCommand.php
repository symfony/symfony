<?php

namespace Symfony\Component\Amqp\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Amqp\Broker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class AmqpMoveCommand extends Command
{
    private $broker;
    private $logger;

    public function __construct(Broker $broker, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->broker = $broker;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('amqp:move')
            ->setDescription('Takes all messages from a queue and sends them to the default exchange with a new routing key.')
            ->setDefinition(array(
                new InputArgument('from', InputArgument::REQUIRED, 'The queue.'),
                new InputArgument('to', InputArgument::REQUIRED, 'The new routing key.'),
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        while (false !== $message = $this->broker->get($from)) {
            $io->comment("Moving a message from $from to $to...");

            if (null !== $this->logger) {
                $this->logger->info('Moving a message from {from} to {to}.', array(
                    'from' => $from,
                    'to' => $to,
                ));
            }

            $this->broker->move($message, $to);
            $this->broker->ack($message);

            if ($output->isDebug()) {
                $io->comment("...message moved from $from to $to.");
            }

            if (null !== $this->logger) {
                $this->logger->debug('...message moved {from} to {to}.', array(
                    'from' => $from,
                    'to' => $to,
                ));
            }
        }
    }
}
