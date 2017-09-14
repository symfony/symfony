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

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class TextDescriptor extends Descriptor
{
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

    private function normalizeAndSortOptionsColumns(array $options)
    {
        foreach ($options as $group => &$opts) {
            $sorted = false;
            foreach ($opts as $class => $opt) {
                if (!is_array($opt) || 0 === count($opt)) {
                    continue;
                }

                unset($opts[$class]);

                if (!$sorted) {
                    $opts = array();
                } else {
                    $opts[] = null;
                }
                $opts[] = sprintf('<info>%s</info>', (new \ReflectionClass($class))->getShortName());
                $opts[] = new TableSeparator();

                sort($opt);
                $sorted = true;
                $opts = array_merge($opts, $opt);
            }

            if (!$sorted) {
                sort($opts);
            }
        }

        return $options;
    }
}
