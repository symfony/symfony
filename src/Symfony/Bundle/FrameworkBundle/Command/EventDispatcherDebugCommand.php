<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A console command for retrieving information about event dispatcher.
 *
 * @author Matthieu Auger <mail@matthieuauger.com>
 */
class EventDispatcherDebugCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('debug:event-dispatcher')
            ->setDefinition(array(
                new InputArgument('event', InputArgument::OPTIONAL, 'An event name'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format  (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
                new InputOption('serviceId', null, InputOption::VALUE_REQUIRED, 'The service id of your event dispatcher', 'event_dispatcher'),
            ))
            ->setDescription('Displays configured listeners for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays all configured listeners:

  <info>php %command.full_name%</info>

To get specific listeners for an event, specify its name:

  <info>php %command.full_name% kernel.request</info>

To get events of another event dispatcher, specify your service id:

  <info>php %command.full_name% --serviceId=my.event_dispatcher</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $dispatcher = $this->getEventDispatcher($input->getOption('serviceId'));

        $options = array();
        if ($event = $input->getArgument('event')) {
            if (!$dispatcher->hasListeners($event)) {
                $io->warning(sprintf('The event "%s" does not have any registered listeners.', $event));

                return;
            }

            $options = array('event' => $event);
        }

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $options['raw_text'] = $input->getOption('raw');
        $options['output'] = $io;
        $helper->describe($io, $dispatcher, $options);
    }

    /**
     * Loads the Event Dispatcher from the container.
     *
     * @param string $serviceId
     *
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher($serviceId)
    {
        $eventDispatcher = $this->getContainer()->get($serviceId);

        if (!$eventDispatcher instanceOf EventDispatcherInterface) {
            throw new \RuntimeException(sprintf('The service id "%s" does not reference an event dispatcher.', $serviceId));
        }

        return $eventDispatcher;
    }
}
