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

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * A console command for retrieving information about event dispatcher.
 *
 * @author Matthieu Auger <mail@matthieuauger.com>
 *
 * @final
 */
#[AsCommand(name: 'debug:event-dispatcher', description: 'Display configured listeners for an application')]
class EventDispatcherDebugCommand extends Command
{
    private const DEFAULT_DISPATCHER = 'event_dispatcher';

    private ContainerInterface $dispatchers;

    public function __construct(ContainerInterface $dispatchers)
    {
        parent::__construct();

        $this->dispatchers = $dispatchers;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('event', InputArgument::OPTIONAL, 'An event name or a part of the event name'),
                new InputOption('dispatcher', null, InputOption::VALUE_REQUIRED, 'To view events of a specific event dispatcher', self::DEFAULT_DISPATCHER),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format  (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
            ])
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
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $options = [];
        $dispatcherServiceName = $input->getOption('dispatcher');
        if (!$this->dispatchers->has($dispatcherServiceName)) {
            $io->getErrorStyle()->error(sprintf('Event dispatcher "%s" is not available.', $dispatcherServiceName));

            return 1;
        }

        $dispatcher = $this->dispatchers->get($dispatcherServiceName);

        if ($event = $input->getArgument('event')) {
            if ($dispatcher->hasListeners($event)) {
                $options = ['event' => $event];
            } else {
                // if there is no direct match, try find partial matches
                $events = $this->searchForEvent($dispatcher, $event);
                if (0 === \count($events)) {
                    $io->getErrorStyle()->warning(sprintf('The event "%s" does not have any registered listeners.', $event));

                    return 0;
                } elseif (1 === \count($events)) {
                    $options = ['event' => $events[array_key_first($events)]];
                } else {
                    $options = ['events' => $events];
                }
            }
        }

        $helper = new DescriptorHelper();

        if (self::DEFAULT_DISPATCHER !== $dispatcherServiceName) {
            $options['dispatcher_service_name'] = $dispatcherServiceName;
        }

        $options['format'] = $input->getOption('format');
        $options['raw_text'] = $input->getOption('raw');
        $options['output'] = $io;
        $helper->describe($io, $dispatcher, $options);

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('event')) {
            $dispatcherServiceName = $input->getOption('dispatcher');
            if ($this->dispatchers->has($dispatcherServiceName)) {
                $dispatcher = $this->dispatchers->get($dispatcherServiceName);
                $suggestions->suggestValues(array_keys($dispatcher->getListeners()));
            }

            return;
        }

        if ($input->mustSuggestOptionValuesFor('dispatcher')) {
            if ($this->dispatchers instanceof ServiceProviderInterface) {
                $suggestions->suggestValues(array_keys($this->dispatchers->getProvidedServices()));
            }

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues((new DescriptorHelper())->getFormats());
        }
    }

    private function searchForEvent(EventDispatcherInterface $dispatcher, string $needle): array
    {
        $output = [];
        $lcNeedle = strtolower($needle);
        $allEvents = array_keys($dispatcher->getListeners());
        foreach ($allEvents as $event) {
            if (str_contains(strtolower($event), $lcNeedle)) {
                $output[] = $event;
            }
        }

        return $output;
    }
}
