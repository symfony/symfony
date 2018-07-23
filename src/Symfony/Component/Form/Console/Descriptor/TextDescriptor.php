<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Console\Descriptor;

use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class TextDescriptor extends Descriptor
{
    protected function describeDefaults(array $options)
    {
        $this->output->section('Built-in form types (Symfony\Component\Form\Extension\Core\Type)');
        $shortClassNames = array_map(function ($fqcn) { return array_slice(explode('\\', $fqcn), -1)[0]; }, $options['core_types']);
        for ($i = 0; $i * 5 < count($shortClassNames); ++$i) {
            $this->output->writeln(' '.implode(', ', array_slice($shortClassNames, $i * 5, 5)));
        }

        $this->output->section('Service form types');
        $this->output->listing($options['service_types']);

        $this->output->section('Type extensions');
        $this->output->listing($options['extensions']);

        $this->output->section('Type guessers');
        $this->output->listing($options['guessers']);
    }

    protected function describeResolvedFormType(ResolvedFormTypeInterface $resolvedFormType, array $options = array())
    {
        $this->collectOptions($resolvedFormType);

        $formOptions = $this->normalizeAndSortOptionsColumns(array_filter(array(
            'own' => $this->ownOptions,
            'overridden' => $this->overriddenOptions,
            'parent' => $this->parentOptions,
            'extension' => $this->extensionOptions,
        )));

        // setting headers and column order
        $tableHeaders = array_intersect_key(array(
            'own' => 'Options',
            'overridden' => 'Overridden options',
            'parent' => 'Parent options',
            'extension' => 'Extension options',
        ), $formOptions);

        $tableRows = array();
        $count = count(max($formOptions));
        for ($i = 0; $i < $count; ++$i) {
            $cells = array();
            foreach (array_keys($tableHeaders) as $group) {
                if (isset($formOptions[$group][$i])) {
                    $option = $formOptions[$group][$i];

                    if (is_string($option) && in_array($option, $this->requiredOptions)) {
                        $option .= ' <info>(required)</info>';
                    }

                    $cells[] = $option;
                } else {
                    $cells[] = null;
                }
            }
            $tableRows[] = $cells;
        }

        $this->output->title(sprintf('%s (Block prefix: "%s")', get_class($resolvedFormType->getInnerType()), $resolvedFormType->getInnerType()->getBlockPrefix()));
        $this->output->table($tableHeaders, $tableRows);

        if ($this->parents) {
            $this->output->section('Parent types');
            $this->output->listing($this->parents);
        }

        if ($this->extensions) {
            $this->output->section('Type extensions');
            $this->output->listing($this->extensions);
        }
    }

    protected function describeOption(OptionsResolver $optionsResolver, array $options)
    {
        $definition = $this->getOptionDefinition($optionsResolver, $options['option']);

        $dump = $this->getDumpFunction();
        $map = array(
            'Required' => 'required',
            'Default' => 'default',
            'Allowed types' => 'allowedTypes',
            'Allowed values' => 'allowedValues',
            'Normalizer' => 'normalizer',
        );
        $rows = array();
        foreach ($map as $label => $name) {
            $value = array_key_exists($name, $definition) ? $dump($definition[$name]) : '-';
            if ('default' === $name && isset($definition['lazy'])) {
                $value = "Value: $value\n\nClosure(s): ".$dump($definition['lazy']);
            }

            $rows[] = array("<info>$label</info>", $value);
            $rows[] = new TableSeparator();
        }
        array_pop($rows);

        $this->output->title(sprintf('%s (%s)', get_class($options['type']), $options['option']));
        $this->output->table(array(), $rows);
    }

    private function normalizeAndSortOptionsColumns(array $options)
    {
        foreach ($options as $group => $opts) {
            $sorted = false;
            foreach ($opts as $class => $opt) {
                if (is_string($class)) {
                    unset($options[$group][$class]);
                }

                if (!is_array($opt) || 0 === count($opt)) {
                    continue;
                }

                if (!$sorted) {
                    $options[$group] = array();
                } else {
                    $options[$group][] = null;
                }
                $options[$group][] = sprintf('<info>%s</info>', (new \ReflectionClass($class))->getShortName());
                $options[$group][] = new TableSeparator();

                sort($opt);
                $sorted = true;
                $options[$group] = array_merge($options[$group], $opt);
            }

            if (!$sorted) {
                sort($options[$group]);
            }
        }

        return $options;
    }

    private function getDumpFunction()
    {
        $cloner = new VarCloner();
        $cloner->addCasters(array('Closure' => function ($c, $a) {
            $prefix = Caster::PREFIX_VIRTUAL;

            return array(
                $prefix.'parameters' => isset($a[$prefix.'parameters']) ? count($a[$prefix.'parameters']->value) : 0,
                $prefix.'file' => $a[$prefix.'file'],
                $prefix.'line' => $a[$prefix.'line'],
            );
        }));
        $dumper = new CliDumper(null, null, CliDumper::DUMP_LIGHT_ARRAY | CliDumper::DUMP_COMMA_SEPARATOR);
        $dumper->setColors($this->output->isDecorated());

        return function ($value) use ($dumper, $cloner) {
            return rtrim($dumper->dump($cloner->cloneVar($value)->withRefHandles(false), true));
        };
    }
}
