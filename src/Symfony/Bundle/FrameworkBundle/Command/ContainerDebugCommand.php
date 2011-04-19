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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;

/**
 * A console command for retrieving information about services
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 */
class ContainerDebugCommand extends Command
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    private $containerBuilder;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A service name (foo)  or search (foo*)'),
                new InputOption('show-private', null, InputOption::VALUE_NONE, 'Use to show public *and* private services'),
            ))
            ->setName('container:debug')
            ->setDescription('Displays current services for an application')
            ->setHelp(<<<EOF
The <info>container:debug</info> displays all configured <comment>public</comment> services:

  <info>container:debug</info>

To get specific information about a service, specify its name:

  <info>container:debug validator</info>

By default, private services are hidden. You can display all services by
using the --show-private flag:

  <info>container:debug --show-private</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        $this->containerBuilder = $this->getContainerBuilder();
        $serviceIds = $this->containerBuilder->getServiceIds();

        // sort so that it reads like an index of services
        asort($serviceIds);

        if ($name) {
            $this->outputService($output, $name);
        } else {
            $showPrivate = $input->getOption('show-private');
            $this->outputServices($output, $serviceIds, $showPrivate);
        }
    }

    protected function outputServices(OutputInterface $output, $serviceIds, $showPrivate = false)
    {
        // set the label to specify public or public+private
        if ($showPrivate) {
            $label = '<comment>Public</comment> and <comment>private</comment> services';
        } else {
            $label = '<comment>Public</comment> services';
        }

        $output->writeln($this->getHelper('formatter')->formatSection('container', $label));

        // loop through to find get space needed and filter private services
        $maxName = 4;
        $maxScope = 6;
        foreach ($serviceIds as $key => $serviceId) {
            $definition = $this->resolveServiceDefinition($serviceId);

            if ($definition instanceof Definition) {
                // filter out private services unless shown explicitly
                if (!$showPrivate && !$definition->isPublic()) {
                    unset($serviceIds[$key]);
                    continue;
                }

                if (strlen($definition->getScope()) > $maxScope) {
                    $maxScope = strlen($definition->getScope());
                }
            }

            if (strlen($serviceId) > $maxName) {
                $maxName = strlen($serviceId);
            }
        }
        $format  = '%-'.$maxName.'s %-'.$maxScope.'s %s';

        // the title field needs extra space to make up for comment tags
        $format1  = '%-'.($maxName + 19).'s %-'.($maxScope + 19).'s %s';
        $output->writeln(sprintf($format1, '<comment>Name</comment>', '<comment>Scope</comment>', '<comment>Class Name</comment>'));

        foreach ($serviceIds as $serviceId) {
            $definition = $this->resolveServiceDefinition($serviceId);

            if ($definition instanceof Definition) {
                $output->writeln(sprintf($format, $serviceId, $definition->getScope(), $definition->getClass()));
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $output->writeln(sprintf($format, $serviceId, 'n/a', sprintf('<comment>alias for</comment> <info>%s</info>', (string) $alias)));
            } else {
                // we have no information (happens with "service_container")
                $service = $definition;
                $output->writeln(sprintf($format, $serviceId, '', get_class($service)));
            }
        }
    }

    /**
     * Renders detailed service information about one service
     */
    protected function outputService(OutputInterface $output, $serviceId)
    {
        $definition = $this->resolveServiceDefinition($serviceId);

        $label = sprintf('Information for service <info>%s</info>', $serviceId);
        $output->writeln($this->getHelper('formatter')->formatSection('container', $label));
        $output->writeln('');

        if ($definition instanceof Definition) {
            $output->writeln(sprintf('<comment>Service Id</comment>   %s', $serviceId));
            $output->writeln(sprintf('<comment>Class</comment>        %s', $definition->getClass()));

            $tags = $definition->getTags() ? implode(', ', array_keys($definition->getTags())) : '-';
            $output->writeln(sprintf('<comment>Tags</comment>         %s', $tags));

            $output->writeln(sprintf('<comment>Scope</comment>        %s', $definition->getScope()));

            $public = $definition->isPublic() ? 'yes' : 'no';
            $output->writeln(sprintf('<comment>Public</comment>       %s', $public));
        } elseif ($definition instanceof Alias) {
            $alias = $definition;
            $output->writeln(sprintf('This service is an alias for the service <info>%s</info>', (string) $alias));
        } else {
            // edge case (but true for "service_container", all we have is the service itself
            $service = $definition;
            $output->writeln(sprintf('<comment>Service Id</comment>   %s', $serviceId));
            $output->writeln(sprintf('<comment>Class</comment>        %s', get_class($service)));
        }
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @see ContainerBuilderDebugDumpPass
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    private function getContainerBuilder()
    {
        $cachedFile = ContainerBuilderDebugDumpPass::getBuilderCacheFilename($this->container);

        if (!file_exists($cachedFile)) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        return unserialize(file_get_contents($cachedFile));
    }

    /**
     * Given an array of service IDs, this returns the array of corresponding
     * Definition and Alias objects that those ids represent.
     *
     * @param string $serviceId The service id to resolve
     * @return \Symfony\Component\DependencyInjection\Definition|\Symfony\Component\DependencyInjection\Alias
     */
    private function resolveServiceDefinition($serviceId)
    {
        if ($this->containerBuilder->hasDefinition($serviceId)) {
            return $this->containerBuilder->getDefinition($serviceId);
        }

        // Some service IDs don't have a Definition, they're simply an Alias
        if ($this->containerBuilder->hasAlias($serviceId)) {
            return $this->containerBuilder->getAlias($serviceId);
        }

        // the service has been injected in some special way, just return the service
        return $this->containerBuilder->get($serviceId);
    }
}
