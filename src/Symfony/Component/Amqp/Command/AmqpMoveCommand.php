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
    private $container;
    private $logger;

    /**
     * @param ContainerInterface $container A PSR11 container from which to load the Broker service
     * @param LoggerInterface|null $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->container = $container;
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
        /** @var Broker $broker */
        $broker = $this->container->get(Broker::class);
        $io = new SymfonyStyle($input, $output);
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        while (false !== $message = $broker->get($from)) {
            $io->comment("Moving a message from $from to $to...");

            if (null !== $this->logger) {
                $this->logger->info('Moving a message from {from} to {to}.', array(
                    'from' => $from,
                    'to' => $to,
                ));
            }

            $broker->move($message, $to);
            $broker->ack($message);

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
