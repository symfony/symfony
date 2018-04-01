<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Command;

use Symphony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputArgument;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A console command for retrieving information about event dispatcher.
 *
 * @author Matthieu Auger <mail@matthieuauger.com>
 *
 * @final
 */
class EventDispatcherDebugCommand extends Command
{
    protected static $defaultName = 'debug:event-dispatcher';
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        parent::__construct();

        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('event', InputArgument::OPTIONAL, 'An event name'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format  (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
            ))
            ->setDescription('Displays configured listeners for an application')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all configured listeners:

  <info>php %command.full_name%</info>

To get specific listeners for an event, specify its name:

  <info>php %command.full_name% kernel.request</info>
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
        $io = new SymphonyStyle($input, $output);

        $options = array();
        if ($event = $input->getArgument('event')) {
            if (!$this->dispatcher->hasListeners($event)) {
                $io->getErrorStyle()->warning(sprintf('The event "%s" does not have any registered listeners.', $event));

                return;
            }

            $options = array('event' => $event);
        }

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $options['raw_text'] = $input->getOption('raw');
        $options['output'] = $io;
        $helper->describe($io, $this->dispatcher, $options);
    }
}
