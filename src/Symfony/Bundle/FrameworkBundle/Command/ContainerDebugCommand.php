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
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * A console command for retrieving information about services.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 *
 * @internal since version 3.4
 */
class ContainerDebugCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'debug:container';

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
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A service name (foo)'),
                new InputOption('show-private', null, InputOption::VALUE_NONE, 'Used to show public *and* private services'),
                new InputOption('show-arguments', null, InputOption::VALUE_NONE, 'Used to show arguments in services'),
                new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Shows all services with a specific tag'),
                new InputOption('tags', null, InputOption::VALUE_NONE, 'Displays tagged services for an application'),
                new InputOption('parameter', null, InputOption::VALUE_REQUIRED, 'Displays a specific parameter for an application'),
                new InputOption('parameters', null, InputOption::VALUE_NONE, 'Displays parameters for an application'),
                new InputOption('types', null, InputOption::VALUE_NONE, 'Displays types (classes/interfaces) available in the container'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
            ))
            ->setDescription('Displays current services for an application')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all configured <comment>public</comment> services:

  <info>php %command.full_name%</info>

To get specific information about a service, specify its name:

  <info>php %command.full_name% validator</info>

To see available types that can be used for autowiring, use the <info>--types</info> flag:

  <info>php %command.full_name% --types</info>

By default, private services are hidden. You can display all services by
using the <info>--show-private</info> flag:

  <info>php %command.full_name% --show-private</info>

Use the --tags option to display tagged <comment>public</comment> services grouped by tag:

  <info>php %command.full_name% --tags</info>

Find all services with a specific tag by specifying the tag name with the <info>--tag</info> option:

  <info>php %command.full_name% --tag=form.type</info>

Use the <info>--parameters</info> option to display all parameters:

  <info>php %command.full_name% --parameters</info>

Display a specific parameter by specifying its name with the <info>--parameter</info> option:

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
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $this->validateInput($input);
        $object = $this->getContainerBuilder();

        if ($input->getOption('types')) {
            $options = array('show_private' => true);
            $options['filter'] = array($this, 'filterToServiceTypes');
        } elseif ($input->getOption('parameters')) {
            $parameters = array();
            foreach ($object->getParameterBag()->all() as $k => $v) {
                $parameters[$k] = $object->resolveEnvPlaceholders($v);
            }
            $object = new ParameterBag($parameters);
            $options = array();
        } elseif ($parameter = $input->getOption('parameter')) {
            $options = array('parameter' => $parameter);
        } elseif ($input->getOption('tags')) {
            $options = array('group_by' => 'tags', 'show_private' => $input->getOption('show-private'));
        } elseif ($tag = $input->getOption('tag')) {
            $options = array('tag' => $tag, 'show_private' => $input->getOption('show-private'));
        } elseif ($name = $input->getArgument('name')) {
            $name = $this->findProperServiceName($input, $errorIo, $object, $name);
            $options = array('id' => $name);
        } else {
            $options = array('show_private' => $input->getOption('show-private'));
        }

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $options['show_arguments'] = $input->getOption('show-arguments');
        $options['raw_text'] = $input->getOption('raw');
        $options['output'] = $io;
        $helper->describe($io, $object, $options);

        if (!$input->getArgument('name') && !$input->getOption('tag') && !$input->getOption('parameter') && $input->isInteractive()) {
            if ($input->getOption('tags')) {
                $errorIo->comment('To search for a specific tag, re-run this command with a search term. (e.g. <comment>debug:container --tag=form.type</comment>)');
            } elseif ($input->getOption('parameters')) {
                $errorIo->comment('To search for a specific parameter, re-run this command with a search term. (e.g. <comment>debug:container --parameter=kernel.debug</comment>)');
            } else {
                $errorIo->comment('To search for a specific service, re-run this command with a search term. (e.g. <comment>debug:container log</comment>)');
            }
        }
    }

    /**
     * Validates input arguments and options.
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
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined with the service name argument.');
        } elseif ((null === $name) && $optionsCount > 1) {
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter can not be combined together.');
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

        $kernel = $this->getApplication()->getKernel();

        if (!$kernel->isDebug() || !(new ConfigCache($kernel->getContainer()->getParameter('debug.container.dump'), true))->isFresh()) {
            $buildContainer = \Closure::bind(function () { return $this->buildContainer(); }, $kernel, \get_class($kernel));
            $container = $buildContainer();
            $container->getCompilerPassConfig()->setRemovingPasses(array());
            $container->compile();
        } else {
            (new XmlFileLoader($container = new ContainerBuilder(), new FileLocator()))->load($kernel->getContainer()->getParameter('debug.container.dump'));
        }

        return $this->containerBuilder = $container;
    }

    private function findProperServiceName(InputInterface $input, SymfonyStyle $io, ContainerBuilder $builder, $name)
    {
        if ($builder->has($name) || !$input->isInteractive()) {
            return $name;
        }

        $matchingServices = $this->findServiceIdsContaining($builder, $name);
        if (empty($matchingServices)) {
            throw new InvalidArgumentException(sprintf('No services found that match "%s".', $name));
        }

        $default = 1 === \count($matchingServices) ? $matchingServices[0] : null;

        return $io->choice('Select one of the following services to display its information', $matchingServices, $default);
    }

    private function findServiceIdsContaining(ContainerBuilder $builder, $name)
    {
        $serviceIds = $builder->getServiceIds();
        $foundServiceIds = array();
        foreach ($serviceIds as $serviceId) {
            if (false === stripos($serviceId, $name)) {
                continue;
            }
            $foundServiceIds[] = $serviceId;
        }

        return $foundServiceIds;
    }

    /**
     * @internal
     */
    public function filterToServiceTypes($serviceId)
    {
        // filter out things that could not be valid class names
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $serviceId)) {
            return false;
        }

        // if the id has a \, assume it is a class
        if (false !== strpos($serviceId, '\\')) {
            return true;
        }

        try {
            new \ReflectionClass($serviceId);

            return true;
        } catch (\ReflectionException $e) {
            // the service id is not a valid class/interface
            return false;
        }
    }
}
