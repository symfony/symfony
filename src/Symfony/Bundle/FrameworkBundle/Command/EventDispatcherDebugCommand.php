<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class EventDispatcherDebugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('event_dispatcher:debug')
            ->addArgument('event', InputArgument::OPTIONAL, 'Show listeners for a event', '')
            ->setDescription('Displays current event-listeners and event-subscribers for an application');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $containerBuilder = $this->getContainerBuilder();

        $listeners = $this->getListeners($containerBuilder);
        $listeners = $this->getEventSubscriber($containerBuilder, $listeners);

        $event = $input->getArgument('event');
        if ($event != '') {
            if (!isset($listeners[$event])) {
                throw new \InvalidArgumentException(sprintf('The event %s does not exist', $event));
            }

            $listeners = $listeners[$event];

            $output->writeln(sprintf('<info>[event_dispatcher]</info> Information for event <info>%s</info>', $event));
        } else {
            ksort($listeners);
            $listeners = array_reduce($listeners, function($result, $elements){
                foreach ($elements as $element) {
                    $result[] = $element;
                }

                return $result;
            });

            $output->writeln('<info>[event_dispatcher]</info> Listeners');
        }

        $table = $this->setupTableNoBorders();
        $table
            ->setRows($listeners)
            ->render($output);
    }

    /**
     * @return TableHelper
     */
    private function setupTableNoBorders()
    {
        $table = $this->getHelperSet()->get('table');
        $table
            ->setHeaders(array('Class', 'Event'))
            ->setCrossingChar('')
            ->setVerticalBorderChar('')
            ->setHorizontalBorderChar('');

        return $table;
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @return array
     */
    private function getListeners(ContainerBuilder $containerBuilder)
    {
        $definitions = $containerBuilder->findTaggedServiceIds('kernel.event_listener');

        $listeners = array();
        foreach ($definitions as $id => $definition) {
            $event = $definition[0]['event'];

            $class = $containerBuilder->getDefinition($id)->getClass();

            $listeners[$event][] = array($class, $event);
        }

        return $listeners;
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @return array
     */
    private function getEventSubscriber(ContainerBuilder $containerBuilder, array $listeners)
    {
        foreach ($containerBuilder->findTaggedServiceIds('kernel.event_subscriber') as $id => $attributes) {
            $class = $containerBuilder->getDefinition($id)->getClass();

            $refClass = new \ReflectionClass($class);
            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';

            if (!$refClass->implementsInterface($interface)) {
                continue;
            }

            $subscribedEvents = $class::getSubscribedEvents();
            $events = array_keys($subscribedEvents);

            foreach ($events as $event) {
                $listeners[$event][] = array($class, $event);
            }
        }

        return $listeners;
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     */
    protected function getContainerBuilder()
    {
        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the EventDispatcher is only available in debug mode.'));
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the EventDispatcher could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }
}