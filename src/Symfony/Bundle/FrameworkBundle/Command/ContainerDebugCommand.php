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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * A console command for retrieving information about services.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 *
 * @internal
 */
class ContainerDebugCommand extends Command
{
    use BuildDebugContainerTrait;

    protected static $defaultName = 'debug:container';
    protected static $defaultDescription = 'Display current services for an application';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A service name (foo)'),
                new InputOption('show-arguments', null, InputOption::VALUE_NONE, 'Show arguments in services'),
                new InputOption('show-hidden', null, InputOption::VALUE_NONE, 'Show hidden (internal) services'),
                new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Show all services with a specific tag'),
                new InputOption('tags', null, InputOption::VALUE_NONE, 'Display tagged services for an application'),
                new InputOption('parameter', null, InputOption::VALUE_REQUIRED, 'Display a specific parameter for an application'),
                new InputOption('parameters', null, InputOption::VALUE_NONE, 'Display parameters for an application'),
                new InputOption('types', null, InputOption::VALUE_NONE, 'Display types (classes/interfaces) available in the container'),
                new InputOption('env-var', null, InputOption::VALUE_REQUIRED, 'Display a specific environment variable used in the container'),
                new InputOption('env-vars', null, InputOption::VALUE_NONE, 'Display environment variables used in the container'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw description'),
                new InputOption('deprecations', null, InputOption::VALUE_NONE, 'Display deprecations generated when compiling and warming up the container'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all configured <comment>public</comment> services:

  <info>php %command.full_name%</info>

To see deprecations generated during container compilation and cache warmup, use the <info>--deprecations</info> option:

  <info>php %command.full_name% --deprecations</info>

To get specific information about a service, specify its name:

  <info>php %command.full_name% validator</info>

To get specific information about a service including all its arguments, use the <info>--show-arguments</info> flag:

  <info>php %command.full_name% validator --show-arguments</info>

To see available types that can be used for autowiring, use the <info>--types</info> flag:

  <info>php %command.full_name% --types</info>

To see environment variables used by the container, use the <info>--env-vars</info> flag:

  <info>php %command.full_name% --env-vars</info>

Display a specific environment variable by specifying its name with the <info>--env-var</info> option:

  <info>php %command.full_name% --env-var=APP_ENV</info>

Use the --tags option to display tagged <comment>public</comment> services grouped by tag:

  <info>php %command.full_name% --tags</info>

Find all services with a specific tag by specifying the tag name with the <info>--tag</info> option:

  <info>php %command.full_name% --tag=form.type</info>

Use the <info>--parameters</info> option to display all parameters:

  <info>php %command.full_name% --parameters</info>

Display a specific parameter by specifying its name with the <info>--parameter</info> option:

  <info>php %command.full_name% --parameter=kernel.debug</info>

By default, internal services are hidden. You can display them
using the <info>--show-hidden</info> flag:

  <info>php %command.full_name% --show-hidden</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $this->validateInput($input);
        $kernel = $this->getApplication()->getKernel();
        $object = $this->getContainerBuilder($kernel);

        if ($input->getOption('env-vars')) {
            $options = ['env-vars' => true];
        } elseif ($envVar = $input->getOption('env-var')) {
            $options = ['env-vars' => true, 'name' => $envVar];
        } elseif ($input->getOption('types')) {
            $options = [];
            $options['filter'] = [$this, 'filterToServiceTypes'];
        } elseif ($input->getOption('parameters')) {
            $parameters = [];
            foreach ($object->getParameterBag()->all() as $k => $v) {
                $parameters[$k] = $object->resolveEnvPlaceholders($v);
            }
            $object = new ParameterBag($parameters);
            $options = [];
        } elseif ($parameter = $input->getOption('parameter')) {
            $options = ['parameter' => $parameter];
        } elseif ($input->getOption('tags')) {
            $options = ['group_by' => 'tags'];
        } elseif ($tag = $input->getOption('tag')) {
            $options = ['tag' => $tag];
        } elseif ($name = $input->getArgument('name')) {
            $name = $this->findProperServiceName($input, $errorIo, $object, $name, $input->getOption('show-hidden'));
            $options = ['id' => $name];
        } elseif ($input->getOption('deprecations')) {
            $options = ['deprecations' => true];
        } else {
            $options = [];
        }

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $options['show_arguments'] = $input->getOption('show-arguments');
        $options['show_hidden'] = $input->getOption('show-hidden');
        $options['raw_text'] = $input->getOption('raw');
        $options['output'] = $io;
        $options['is_debug'] = $kernel->isDebug();

        try {
            $helper->describe($io, $object, $options);

            if (isset($options['id']) && isset($kernel->getContainer()->getRemovedIds()[$options['id']])) {
                $errorIo->note(sprintf('The "%s" service or alias has been removed or inlined when the container was compiled.', $options['id']));
            }
        } catch (ServiceNotFoundException $e) {
            if ('' !== $e->getId() && '@' === $e->getId()[0]) {
                throw new ServiceNotFoundException($e->getId(), $e->getSourceId(), null, [substr($e->getId(), 1)]);
            }

            throw $e;
        }

        if (!$input->getArgument('name') && !$input->getOption('tag') && !$input->getOption('parameter') && !$input->getOption('env-vars') && !$input->getOption('env-var') && $input->isInteractive()) {
            if ($input->getOption('tags')) {
                $errorIo->comment('To search for a specific tag, re-run this command with a search term. (e.g. <comment>debug:container --tag=form.type</comment>)');
            } elseif ($input->getOption('parameters')) {
                $errorIo->comment('To search for a specific parameter, re-run this command with a search term. (e.g. <comment>debug:container --parameter=kernel.debug</comment>)');
            } elseif (!$input->getOption('deprecations')) {
                $errorIo->comment('To search for a specific service, re-run this command with a search term. (e.g. <comment>debug:container log</comment>)');
            }
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('format')) {
            $helper = new DescriptorHelper();
            $suggestions->suggestValues($helper->getFormats());

            return;
        }

        $kernel = $this->getApplication()->getKernel();
        $object = $this->getContainerBuilder($kernel);

        if ($input->mustSuggestArgumentValuesFor('name')
            && !$input->getOption('tag') && !$input->getOption('tags')
            && !$input->getOption('parameter') && !$input->getOption('parameters')
            && !$input->getOption('env-var') && !$input->getOption('env-vars')
            && !$input->getOption('types') && !$input->getOption('deprecations')
        ) {
            $suggestions->suggestValues($this->findServiceIdsContaining(
                $object,
                $input->getCompletionValue(),
                (bool) $input->getOption('show-hidden')
            ));

            return;
        }

        if ($input->mustSuggestOptionValuesFor('tag')) {
            $suggestions->suggestValues($object->findTags());

            return;
        }

        if ($input->mustSuggestOptionValuesFor('parameter')) {
            $suggestions->suggestValues(array_keys($object->getParameterBag()->all()));
        }
    }

    /**
     * Validates input arguments and options.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateInput(InputInterface $input)
    {
        $options = ['tags', 'tag', 'parameters', 'parameter'];

        $optionsCount = 0;
        foreach ($options as $option) {
            if ($input->getOption($option)) {
                ++$optionsCount;
            }
        }

        $name = $input->getArgument('name');
        if ((null !== $name) && ($optionsCount > 0)) {
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter cannot be combined with the service name argument.');
        } elseif ((null === $name) && $optionsCount > 1) {
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter cannot be combined together.');
        }
    }

    private function findProperServiceName(InputInterface $input, SymfonyStyle $io, ContainerBuilder $builder, string $name, bool $showHidden): string
    {
        $name = ltrim($name, '\\');

        if ($builder->has($name) || !$input->isInteractive()) {
            return $name;
        }

        $matchingServices = $this->findServiceIdsContaining($builder, $name, $showHidden);
        if (empty($matchingServices)) {
            throw new InvalidArgumentException(sprintf('No services found that match "%s".', $name));
        }

        if (1 === \count($matchingServices)) {
            return $matchingServices[0];
        }

        return $io->choice('Select one of the following services to display its information', $matchingServices);
    }

    private function findServiceIdsContaining(ContainerBuilder $builder, string $name, bool $showHidden): array
    {
        $serviceIds = $builder->getServiceIds();
        $foundServiceIds = $foundServiceIdsIgnoringBackslashes = [];
        foreach ($serviceIds as $serviceId) {
            if (!$showHidden && str_starts_with($serviceId, '.')) {
                continue;
            }
            if (false !== stripos(str_replace('\\', '', $serviceId), $name)) {
                $foundServiceIdsIgnoringBackslashes[] = $serviceId;
            }
            if ('' === $name || false !== stripos($serviceId, $name)) {
                $foundServiceIds[] = $serviceId;
            }
        }

        return $foundServiceIds ?: $foundServiceIdsIgnoringBackslashes;
    }

    /**
     * @internal
     */
    public function filterToServiceTypes(string $serviceId): bool
    {
        // filter out things that could not be valid class names
        if (!preg_match('/(?(DEFINE)(?<V>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))^(?&V)(?:\\\\(?&V))*+(?: \$(?&V))?$/', $serviceId)) {
            return false;
        }

        // if the id has a \, assume it is a class
        if (str_contains($serviceId, '\\')) {
            return true;
        }

        return class_exists($serviceId) || interface_exists($serviceId, false);
    }
}
