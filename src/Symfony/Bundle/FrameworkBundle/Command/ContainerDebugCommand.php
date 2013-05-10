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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * A console command for retrieving information about services
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 */
class ContainerDebugCommand extends ContainerAwareCommand
{
    /**
     * @var ContainerBuilder|null
     */
    protected $containerBuilder;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('container:debug')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A service name (foo)'),
                new InputOption('show-private', null, InputOption::VALUE_NONE, 'Use to show public *and* private services'),
                new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Show all services with a specific tag'),
                new InputOption('tags', null, InputOption::VALUE_NONE, 'Displays tagged services for an application'),
                new InputOption('parameter', null, InputOption::VALUE_REQUIRED, 'Displays a specific parameter for an application'),
                new InputOption('parameters', null, InputOption::VALUE_NONE, 'Displays parameters for an application')
            ))
            ->setDescription('Displays current services for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays all configured <comment>public</comment> services:

  <info>php %command.full_name%</info>

To get specific information about a service, specify its name:

  <info>php %command.full_name% validator</info>

By default, private services are hidden. You can display all services by
using the --show-private flag:

  <info>php %command.full_name% --show-private</info>

Use the --tags option to display tagged <comment>public</comment> services grouped by tag:

  <info>php %command.full_name% --tags</info>

Find all services with a specific tag by specifying the tag name with the --tag option:

  <info>php %command.full_name% --tag=form.type</info>

Use the --parameters option to display all parameters:

  <info>php %command.full_name% --parameters</info>

Display a specific parameter by specifying his name with the --parameter option:

  <info>php %command.full_name% --parameter=kernel.debug</info>
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
        $this->validateInput($input);

        $this->containerBuilder = $this->getContainerBuilder();

        if ($input->getOption('parameters')) {
            $parameters = $this->getContainerBuilder()->getParameterBag()->all();

            // Sort parameters alphabetically
            ksort($parameters);

            $this->outputParameters($output, $parameters);

            return;
        }

        $parameter = $input->getOption('parameter');
        if (null !== $parameter) {
            $output->write($this->formatParameter($this->getContainerBuilder()->getParameter($parameter)));

            return;
        }

        if ($input->getOption('tags')) {
            $this->outputTags($output, $input->getOption('show-private'));

            return;
        }

        $tag = $input->getOption('tag');
        if (null !== $tag) {
            $serviceIds = array_keys($this->containerBuilder->findTaggedServiceIds($tag));
        } else {
            $serviceIds = $this->containerBuilder->getServiceIds();
        }

        // sort so that it reads like an index of services
        asort($serviceIds);

        $name = $name = $input->getArgument('name');
        if ($name) {
            $this->outputService($output, $name);
        } else {
            $this->outputServices($output, $serviceIds, $input->getOption('show-private'), $tag);
        }
    }

    protected function validateInput(InputInterface $input)
    {
        $options = array('tags', 'tag', 'parameters', 'parameter');

        $optionsCount = 0;
        foreach ($options as $option) {
            if ($input->getOption($option)) {
                $optionsCount++;
            }
        }

        $name = $input->getArgument('name');
        if ((null !== $name) && ($optionsCount > 0)) {
            throw new \InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined with the service name argument.');
        } elseif ((null === $name) && $optionsCount > 1) {
            throw new \InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined together.');
        }
    }

    protected function outputServices(OutputInterface $output, $serviceIds, $showPrivate = false, $showTagAttributes = null)
    {
        // set the label to specify public or public+private
        if ($showPrivate) {
            $label = '<comment>Public</comment> and <comment>private</comment> services';
        } else {
            $label = '<comment>Public</comment> services';
        }
        if ($showTagAttributes) {
            $label .= ' with tag <info>'.$showTagAttributes.'</info>';
        }

        $output->writeln($this->getHelper('formatter')->formatSection('container', $label));

        $headerTags = array();
        foreach ($serviceIds as $key => $serviceId) {
            $definition = $this->resolveServiceDefinition($serviceId);

            if ($definition instanceof Definition) {
                // filter out private services unless shown explicitly
                if (!$showPrivate && !$definition->isPublic()) {
                    unset($serviceIds[$key]);
                    continue;
                }

                if (null !== $showTagAttributes) {
                    $tags = $definition->getTag($showTagAttributes);
                    foreach ($tags as $tag) {
                        foreach (array_keys($tag) as $key) {
                            if (!in_array($key, $headerTags, true)) {
                                $headerTags[] = $key;
                            }
                        }
                    }
                }
            }
        }

        $table = $this->getHelperSet()->get('table');
        $table->setHeaders($this->buildArgumentsArray('Service Id', 'Scope', 'Class Name', $headerTags));

        foreach ($serviceIds as $serviceId) {
            $definition = $this->resolveServiceDefinition($serviceId);

            if ($definition instanceof Definition) {
                if (null !== $showTagAttributes) {
                    foreach ($definition->getTag($showTagAttributes) as $key => $tag) {
                        $tagValues = array();
                        foreach ($headerTags as $tagName) {
                            $tagValues[] = isset($tag[$tagName]) ? $tag[$tagName] : "";
                        }
                        if (0 === $key) {
                            $table->addRow($this->buildArgumentsArray($serviceId, $definition->getScope(), $definition->getClass(), $tagValues));
                        } else {
                            $table->addRow($this->buildArgumentsArray('  "', '', '', $tagValues));
                        }
                    }
                } else {
                    $table->addRow($this->buildArgumentsArray($serviceId, $definition->getScope(), $definition->getClass()));
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $table->addRow($this->buildArgumentsArray($serviceId, 'n/a', sprintf('alias for %s', (string) $alias), count($headerTags) ? array_fill(0, count($headerTags), "") : array()));
            } else {
                // we have no information (happens with "service_container")
                $service = $definition;
                $table->addRow($this->buildArgumentsArray($this->buildArgumentsArray($serviceId, '', get_class($service), count($headerTags) ? array_fill(0, count($headerTags), "") : array())));
            }
        }

        $table->render($output);
    }

    protected function buildArgumentsArray($serviceId, $scope, $className, array $tagAttributes = array())
    {
        $arguments = array($serviceId);
        foreach ($tagAttributes as $tagAttribute) {
            $arguments[] = $tagAttribute;
        }
        $arguments[] = $scope;
        $arguments[] = $className;

        return $arguments;
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
            $output->writeln(sprintf('<comment>Service Id</comment>       %s', $serviceId));
            $output->writeln(sprintf('<comment>Class</comment>            %s', $definition->getClass() ?: "-"));

            $tags = $definition->getTags();
            if (count($tags)) {
                $output->writeln('<comment>Tags</comment>');
                foreach ($tags as $tagName => $tagData) {
                    foreach ($tagData as $singleTagData) {
                        $output->writeln(sprintf('    - %-30s (%s)', $tagName, implode(', ', array_map(function($key, $value) {
                            return sprintf('<info>%s</info>: %s', $key, $value);
                        }, array_keys($singleTagData), array_values($singleTagData)))));
                    }
                }
            } else {
                $output->writeln('<comment>Tags</comment>             -');
            }

            $output->writeln(sprintf('<comment>Scope</comment>            %s', $definition->getScope()));

            $public = $definition->isPublic() ? 'yes' : 'no';
            $output->writeln(sprintf('<comment>Public</comment>           %s', $public));

            $synthetic = $definition->isSynthetic() ? 'yes' : 'no';
            $output->writeln(sprintf('<comment>Synthetic</comment>        %s', $synthetic));

            $file = $definition->getFile() ? $definition->getFile() : '-';
            $output->writeln(sprintf('<comment>Required File</comment>    %s', $file));
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

    protected function outputParameters(OutputInterface $output, $parameters)
    {
        $output->writeln($this->getHelper('formatter')->formatSection('container', 'List of parameters'));

        $terminalDimensions = $this->getApplication()->getTerminalDimensions();
        $maxTerminalWidth = $terminalDimensions[0];
        $maxParameterWidth = 0;
        $maxValueWidth = 0;

        // Determine max parameter & value length
        foreach ($parameters as $parameter => $value) {
            $parameterWidth = strlen($parameter);
            if ($parameterWidth > $maxParameterWidth) {
                $maxParameterWidth = $parameterWidth;
            }

            $valueWith = strlen($this->formatParameter($value));
            if ($valueWith > $maxValueWidth) {
                $maxValueWidth = $valueWith;
            }
        }

        $maxValueWidth = min($maxValueWidth, $maxTerminalWidth - $maxParameterWidth - 1);

        $formatTitle = '%-'.($maxParameterWidth + 19).'s %-'.($maxValueWidth + 19).'s';
        $format = '%-'.$maxParameterWidth.'s %-'.$maxValueWidth.'s';

        $output->writeln(sprintf($formatTitle, '<comment>Parameter</comment>', '<comment>Value</comment>'));

        foreach ($parameters as $parameter => $value) {
            $splits = str_split($this->formatParameter($value), $maxValueWidth);

            foreach ($splits as $index => $split) {
                if (0 === $index) {
                    $output->writeln(sprintf($format, $parameter, $split));
                } else {
                    $output->writeln(sprintf($format, ' ', $split));
                }
            }
        }
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     *
     * @throws \LogicException
     */
    protected function getContainerBuilder()
    {
        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }

    /**
     * Given an array of service IDs, this returns the array of corresponding
     * Definition and Alias objects that those ids represent.
     *
     * @param string $serviceId The service id to resolve
     *
     * @return Definition|Alias
     */
    protected function resolveServiceDefinition($serviceId)
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

    /**
     * Renders list of tagged services grouped by tag
     *
     * @param OutputInterface $output
     * @param Boolean         $showPrivate
     */
    protected function outputTags(OutputInterface $output, $showPrivate = false)
    {
        $tags = $this->containerBuilder->findTags();
        asort($tags);

        $label = 'Tagged services';
        $output->writeln($this->getHelper('formatter')->formatSection('container', $label));

        foreach ($tags as $tag) {
            $serviceIds = $this->containerBuilder->findTaggedServiceIds($tag);

            foreach ($serviceIds as $serviceId => $attributes) {
                $definition = $this->resolveServiceDefinition($serviceId);
                if ($definition instanceof Definition) {
                    if (!$showPrivate && !$definition->isPublic()) {
                        unset($serviceIds[$serviceId]);
                        continue;
                    }
                }
            }

            if (count($serviceIds) === 0) {
                continue;
            }

            $output->writeln($this->getHelper('formatter')->formatSection('tag', $tag));

            foreach ($serviceIds as $serviceId => $attributes) {
                $output->writeln($serviceId);
            }

            $output->writeln('');
        }
    }

    protected function formatParameter($value)
    {
        if (is_bool($value) || is_array($value) || (null === $value)) {
            return json_encode($value);
        }

        return $value;
    }
}
