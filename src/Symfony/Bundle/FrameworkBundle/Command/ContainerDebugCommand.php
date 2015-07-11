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
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * A console command for retrieving information about services.
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
            ->setName('debug:container')
            ->setAliases(array(
                'container:debug',
            ))
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A service name (foo)'),
                new InputOption('show-private', null, InputOption::VALUE_NONE, 'Used to show public *and* private services'),
                new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Shows all services with a specific tag'),
                new InputOption('tags', null, InputOption::VALUE_NONE, 'Displays tagged services for an application'),
                new InputOption('parameter', null, InputOption::VALUE_REQUIRED, 'Displays a specific parameter for an application'),
                new InputOption('parameters', null, InputOption::VALUE_NONE, 'Displays parameters for an application'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'To output description in other formats', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
            ))
            ->setDescription('Displays current services for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays all configured <comment>public</comment> services:

  <info>php %command.full_name%</info>

To get specific information about a service, specify its name:

  <info>php %command.full_name% validator</info>

By default, private services are hidden. You can display all services by
using the <info>--show-private</info> flag:

  <info>php %command.full_name% --show-private</info>

Use the --tags option to display tagged <comment>public</comment> services grouped by tag:

  <info>php %command.full_name% --tags</info>

Find all services with a specific tag by specifying the tag name with the <info>--tag</info> option:

  <info>php %command.full_name% --tag=form.type</info>

Use the <info>--parameters</info> option to display all parameters:

  <info>php %command.full_name% --parameters</info>

Display a specific parameter by specifying his name with the <info>--parameter</info> option:

  <info>php %command.full_name% --parameter=kernel.debug</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        if ($input->getOption('parameters')) {
            $object = $this->getContainerBuilder()->getParameterBag();
            $options = array();
        } elseif ($parameter = $input->getOption('parameter')) {
            $object = $this->getContainerBuilder();
            $options = array('parameter' => $parameter);
        } elseif ($input->getOption('tags')) {
            $object = $this->getContainerBuilder();
            $options = array('group_by' => 'tags', 'show_private' => $input->getOption('show-private'));
        } elseif ($tag = $input->getOption('tag')) {
            $object = $this->getContainerBuilder();
            $options = array('tag' => $tag, 'show_private' => $input->getOption('show-private'));
        } elseif ($name = $input->getArgument('name')) {
            $object = $this->getContainerBuilder();
            $name = $this->findProperServiceName($input, $output, $object, $name);
            $options = array('id' => $name);
        } else {
            $object = $this->getContainerBuilder();
            $options = array('show_private' => $input->getOption('show-private'));
        }

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $options['raw_text'] = $input->getOption('raw');
        $helper->describe($output, $object, $options);

        if (!$input->getArgument('name') && $input->isInteractive()) {
            $output->writeln('To search for a service, re-run this command with a search term. <comment>debug:container log</comment>');
        }
    }

    /**
     * Validates input arguments and options.
     *
     * @param InputInterface $input
     *
     * @throws \InvalidArgumentException
     */
    protected function validateInput(InputInterface $input)
    {
        $options = array('tags', 'tag', 'parameters', 'parameter');

        $optionsCount = 0;
        foreach ($options as $option) {
            if ($input->getOption($option)) {
                ++$optionsCount;
            }
        }

        $name = $input->getArgument('name');
        if ((null !== $name) && ($optionsCount > 0)) {
            throw new \InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined with the service name argument.');
        } elseif ((null === $name) && $optionsCount > 1) {
            throw new \InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined together.');
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
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $this->containerBuilder = $container;
    }

    private function findProperServiceName(InputInterface $input, OutputInterface $output, ContainerBuilder $builder, $name)
    {
        if ($builder->has($name) || !$input->isInteractive()) {
            return $name;
        }

        $matchingServices = $this->findServiceIdsContaining($builder, $name);
        if (empty($matchingServices)) {
            throw new \InvalidArgumentException(sprintf('No services found that match "%s".', $name));
        }

        $question = new ChoiceQuestion('Choose a number for more information on the service', $matchingServices);
        $question->setErrorMessage('Service %s is invalid.');

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function findServiceIdsContaining(ContainerBuilder $builder, $name)
    {
        $serviceIds = $builder->getServiceIds();
        $foundServiceIds = array();
        $name = strtolower($name);
        foreach ($serviceIds as $serviceId) {
            if (false === strpos($serviceId, $name)) {
                continue;
            }
            $foundServiceIds[] = $serviceId;
        }

        return $foundServiceIds;
    }
}
