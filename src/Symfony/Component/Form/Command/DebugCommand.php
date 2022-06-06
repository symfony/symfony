<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\Console\Helper\DescriptorHelper;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

/**
 * A console command for retrieving information about form types.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:form';
    protected static $defaultDescription = 'Display form type information';

    private $formRegistry;
    private $namespaces;
    private $types;
    private $extensions;
    private $guessers;
    private $fileLinkFormatter;

    public function __construct(FormRegistryInterface $formRegistry, array $namespaces = ['Symfony\Component\Form\Extension\Core\Type'], array $types = [], array $extensions = [], array $guessers = [], FileLinkFormatter $fileLinkFormatter = null)
    {
        parent::__construct();

        $this->formRegistry = $formRegistry;
        $this->namespaces = $namespaces;
        $this->types = $types;
        $this->extensions = $extensions;
        $this->guessers = $guessers;
        $this->fileLinkFormatter = $fileLinkFormatter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('class', InputArgument::OPTIONAL, 'The form type class'),
                new InputArgument('option', InputArgument::OPTIONAL, 'The form type option'),
                new InputOption('show-deprecated', null, InputOption::VALUE_NONE, 'Display deprecated options in form types'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt or json)', 'txt'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays information about form types.

  <info>php %command.full_name%</info>

The command lists all built-in types, services types, type extensions and
guessers currently available.

  <info>php %command.full_name% Symfony\Component\Form\Extension\Core\Type\ChoiceType</info>
  <info>php %command.full_name% ChoiceType</info>

The command lists all defined options that contains the given form type,
as well as their parents and type extensions.

  <info>php %command.full_name% ChoiceType choice_value</info>

Use the <info>--show-deprecated</info> option to display form types with
deprecated options or the deprecated options of the given form type:

  <info>php %command.full_name% --show-deprecated</info>
  <info>php %command.full_name% ChoiceType --show-deprecated</info>

The command displays the definition of the given option name.

  <info>php %command.full_name% --format=json</info>

The command lists everything in a machine readable json format.
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

        if (null === $class = $input->getArgument('class')) {
            $object = null;
            $options['core_types'] = $this->getCoreTypes();
            $options['service_types'] = array_values(array_diff($this->types, $options['core_types']));
            if ($input->getOption('show-deprecated')) {
                $options['core_types'] = $this->filterTypesByDeprecated($options['core_types']);
                $options['service_types'] = $this->filterTypesByDeprecated($options['service_types']);
            }
            $options['extensions'] = $this->extensions;
            $options['guessers'] = $this->guessers;
            foreach ($options as $k => $list) {
                sort($options[$k]);
            }
        } else {
            if (!class_exists($class) || !is_subclass_of($class, FormTypeInterface::class)) {
                $class = $this->getFqcnTypeClass($input, $io, $class);
            }
            $resolvedType = $this->formRegistry->getType($class);

            if ($option = $input->getArgument('option')) {
                $object = $resolvedType->getOptionsResolver();

                if (!$object->isDefined($option)) {
                    $message = sprintf('Option "%s" is not defined in "%s".', $option, \get_class($resolvedType->getInnerType()));

                    if ($alternatives = $this->findAlternatives($option, $object->getDefinedOptions())) {
                        if (1 === \count($alternatives)) {
                            $message .= "\n\nDid you mean this?\n    ";
                        } else {
                            $message .= "\n\nDid you mean one of these?\n    ";
                        }
                        $message .= implode("\n    ", $alternatives);
                    }

                    throw new InvalidArgumentException($message);
                }

                $options['type'] = $resolvedType->getInnerType();
                $options['option'] = $option;
            } else {
                $object = $resolvedType;
            }
        }

        $helper = new DescriptorHelper($this->fileLinkFormatter);
        $options['format'] = $input->getOption('format');
        $options['show_deprecated'] = $input->getOption('show-deprecated');
        $helper->describe($io, $object, $options);

        return 0;
    }

    private function getFqcnTypeClass(InputInterface $input, SymfonyStyle $io, string $shortClassName): string
    {
        $classes = $this->getFqcnTypeClasses($shortClassName);

        if (0 === $count = \count($classes)) {
            $message = sprintf("Could not find type \"%s\" into the following namespaces:\n    %s", $shortClassName, implode("\n    ", $this->namespaces));

            $allTypes = array_merge($this->getCoreTypes(), $this->types);
            if ($alternatives = $this->findAlternatives($shortClassName, $allTypes)) {
                if (1 === \count($alternatives)) {
                    $message .= "\n\nDid you mean this?\n    ";
                } else {
                    $message .= "\n\nDid you mean one of these?\n    ";
                }
                $message .= implode("\n    ", $alternatives);
            }

            throw new InvalidArgumentException($message);
        }
        if (1 === $count) {
            return $classes[0];
        }
        if (!$input->isInteractive()) {
            throw new InvalidArgumentException(sprintf("The type \"%s\" is ambiguous.\n\nDid you mean one of these?\n    %s.", $shortClassName, implode("\n    ", $classes)));
        }

        return $io->choice(sprintf("The type \"%s\" is ambiguous.\n\nSelect one of the following form types to display its information:", $shortClassName), $classes, $classes[0]);
    }

    private function getFqcnTypeClasses(string $shortClassName): array
    {
        $classes = [];
        sort($this->namespaces);
        foreach ($this->namespaces as $namespace) {
            if (class_exists($fqcn = $namespace.'\\'.$shortClassName)) {
                $classes[] = $fqcn;
            } elseif (class_exists($fqcn = $namespace.'\\'.ucfirst($shortClassName))) {
                $classes[] = $fqcn;
            } elseif (class_exists($fqcn = $namespace.'\\'.ucfirst($shortClassName).'Type')) {
                $classes[] = $fqcn;
            } elseif (str_ends_with($shortClassName, 'type') && class_exists($fqcn = $namespace.'\\'.ucfirst(substr($shortClassName, 0, -4).'Type'))) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    private function getCoreTypes(): array
    {
        $coreExtension = new CoreExtension();
        $loadTypesRefMethod = (new \ReflectionObject($coreExtension))->getMethod('loadTypes');
        $loadTypesRefMethod->setAccessible(true);
        $coreTypes = $loadTypesRefMethod->invoke($coreExtension);
        $coreTypes = array_map(function (FormTypeInterface $type) { return \get_class($type); }, $coreTypes);
        sort($coreTypes);

        return $coreTypes;
    }

    private function filterTypesByDeprecated(array $types): array
    {
        $typesWithDeprecatedOptions = [];
        foreach ($types as $class) {
            $optionsResolver = $this->formRegistry->getType($class)->getOptionsResolver();
            foreach ($optionsResolver->getDefinedOptions() as $option) {
                if ($optionsResolver->isDeprecated($option)) {
                    $typesWithDeprecatedOptions[] = $class;
                    break;
                }
            }
        }

        return $typesWithDeprecatedOptions;
    }

    private function findAlternatives(string $name, array $collection): array
    {
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $threshold = 1e3;
        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) { return $lev < 2 * $threshold; });
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);

        return array_keys($alternatives);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('class')) {
            $suggestions->suggestValues(array_merge($this->getCoreTypes(), $this->types));

            return;
        }

        if ($input->mustSuggestArgumentValuesFor('option') && null !== $class = $input->getArgument('class')) {
            $this->completeOptions($class, $suggestions);

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $helper = new DescriptorHelper();
            $suggestions->suggestValues($helper->getFormats());
        }
    }

    private function completeOptions(string $class, CompletionSuggestions $suggestions): void
    {
        if (!class_exists($class) || !is_subclass_of($class, FormTypeInterface::class)) {
            $classes = $this->getFqcnTypeClasses($class);

            if (1 === \count($classes)) {
                $class = $classes[0];
            }
        }

        if (!$this->formRegistry->hasType($class)) {
            return;
        }

        $resolvedType = $this->formRegistry->getType($class);
        $suggestions->suggestValues($resolvedType->getOptionsResolver()->getDefinedOptions());
    }
}
