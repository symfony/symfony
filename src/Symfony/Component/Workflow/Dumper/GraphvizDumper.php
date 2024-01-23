<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Dumper;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;

/**
 * GraphvizDumper dumps a workflow as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (https://graphviz.org/):
 *
 *   dot -Tpng workflow.dot > workflow.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class GraphvizDumper implements DumperInterface
{
    // All values should be strings
    protected static $defaultOptions = [
        'graph' => ['ratio' => 'compress', 'rankdir' => 'LR'],
        'node' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => '#333333', 'fillcolor' => 'lightblue', 'fixedsize' => 'false', 'width' => '1'],
        'edge' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => '#333333', 'arrowhead' => 'normal', 'arrowsize' => '0.5'],
    ];

    /**
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes (places + transitions)
     *  * edge: The default options for edges
     */
    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string
    {
        $withMetadata = $options['with-metadata'] ?? false;

        $places = $this->findPlaces($definition, $withMetadata, $marking);
        $transitions = $this->findTransitions($definition, $withMetadata);
        $edges = $this->findEdges($definition);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        $label = $this->formatLabel($definition, $withMetadata, $options);

        return $this->startDot($options, $label)
            .$this->addPlaces($places, $withMetadata)
            .$this->addTransitions($transitions, $withMetadata)
            .$this->addEdges($edges)
            .$this->endDot();
    }

    /**
     * @internal
     */
    protected function findPlaces(Definition $definition, bool $withMetadata, ?Marking $marking = null): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $places = [];

        foreach ($definition->getPlaces() as $place) {
            $attributes = [];
            if (\in_array($place, $definition->getInitialPlaces(), true)) {
                $attributes['style'] = 'filled';
            }
            if ($marking?->has($place)) {
                $attributes['color'] = '#FF0000';
                $attributes['shape'] = 'doublecircle';
            }
            $backgroundColor = $workflowMetadata->getMetadata('bg_color', $place);
            if (null !== $backgroundColor) {
                $attributes['style'] = 'filled';
                $attributes['fillcolor'] = $backgroundColor;
            }
            if ($withMetadata) {
                $attributes['metadata'] = $workflowMetadata->getPlaceMetadata($place);
            }
            $label = $workflowMetadata->getMetadata('label', $place);
            if (null !== $label) {
                $attributes['name'] = $label;
                if ($withMetadata) {
                    // Don't include label in metadata if already used as name
                    unset($attributes['metadata']['label']);
                }
            }
            $places[$place] = [
                'attributes' => $attributes,
            ];
        }

        return $places;
    }

    /**
     * @internal
     */
    protected function findTransitions(Definition $definition, bool $withMetadata): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $transitions = [];

        foreach ($definition->getTransitions() as $transition) {
            $attributes = ['shape' => 'box', 'regular' => true];

            $backgroundColor = $workflowMetadata->getMetadata('bg_color', $transition);
            if (null !== $backgroundColor) {
                $attributes['style'] = 'filled';
                $attributes['fillcolor'] = $backgroundColor;
            }
            $name = $workflowMetadata->getMetadata('label', $transition) ?? $transition->getName();

            $metadata = [];
            if ($withMetadata) {
                $metadata = $workflowMetadata->getTransitionMetadata($transition);
                unset($metadata['label']);
            }

            $transitions[] = [
                'attributes' => $attributes,
                'name' => $name,
                'metadata' => $metadata,
            ];
        }

        return $transitions;
    }

    /**
     * @internal
     */
    protected function addPlaces(array $places, float $withMetadata): string
    {
        $code = '';

        foreach ($places as $id => $place) {
            if (isset($place['attributes']['name'])) {
                $placeName = $place['attributes']['name'];
                unset($place['attributes']['name']);
            } else {
                $placeName = $id;
            }

            if ($withMetadata) {
                $escapedLabel = sprintf('<<B>%s</B>%s>', $this->escape($placeName), $this->addMetadata($place['attributes']['metadata']));
                // Don't include metadata in default attributes used to format the place
                unset($place['attributes']['metadata']);
            } else {
                $escapedLabel = sprintf('"%s"', $this->escape($placeName));
            }

            $code .= sprintf("  place_%s [label=%s, shape=circle%s];\n", $this->dotize($id), $escapedLabel, $this->addAttributes($place['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function addTransitions(array $transitions, bool $withMetadata): string
    {
        $code = '';

        foreach ($transitions as $i => $place) {
            if ($withMetadata) {
                $escapedLabel = sprintf('<<B>%s</B>%s>', $this->escape($place['name']), $this->addMetadata($place['metadata']));
            } else {
                $escapedLabel = '"'.$this->escape($place['name']).'"';
            }

            $code .= sprintf("  transition_%s [label=%s,%s];\n", $this->dotize($i), $escapedLabel, $this->addAttributes($place['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function findEdges(Definition $definition): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $dotEdges = [];

        foreach ($definition->getTransitions() as $i => $transition) {
            $transitionName = $workflowMetadata->getMetadata('label', $transition) ?? $transition->getName();

            foreach ($transition->getFroms() as $from) {
                $dotEdges[] = [
                    'from' => $from,
                    'to' => $transitionName,
                    'direction' => 'from',
                    'transition_number' => $i,
                ];
            }
            foreach ($transition->getTos() as $to) {
                $dotEdges[] = [
                    'from' => $transitionName,
                    'to' => $to,
                    'direction' => 'to',
                    'transition_number' => $i,
                ];
            }
        }

        return $dotEdges;
    }

    /**
     * @internal
     */
    protected function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $edge) {
            if ('from' === $edge['direction']) {
                $code .= sprintf("  place_%s -> transition_%s [style=\"solid\"];\n",
                    $this->dotize($edge['from']),
                    $this->dotize($edge['transition_number'])
                );
            } else {
                $code .= sprintf("  transition_%s -> place_%s [style=\"solid\"];\n",
                    $this->dotize($edge['transition_number']),
                    $this->dotize($edge['to'])
                );
            }
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function startDot(array $options, string $label): string
    {
        return sprintf("digraph workflow {\n  %s%s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($options['graph']),
            '""' !== $label && '<>' !== $label ? sprintf(' label=%s', $label) : '',
            $this->addOptions($options['node']),
            $this->addOptions($options['edge'])
        );
    }

    /**
     * @internal
     */
    protected function endDot(): string
    {
        return "}\n";
    }

    /**
     * @internal
     */
    protected function dotize(string $id): string
    {
        return hash('sha1', $id);
    }

    /**
     * @internal
     */
    protected function escape(string|bool $value): string
    {
        return \is_bool($value) ? ($value ? '1' : '0') : addslashes($value);
    }

    /**
     * @internal
     */
    protected function addAttributes(array $attributes): string
    {
        $code = [];

        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $this->escape($v));
        }

        return $code ? ' '.implode(' ', $code) : '';
    }

    /**
     * Handles the label of the graph depending on whether a label was set in CLI,
     * if metadata should be included and if there are any.
     *
     * The produced label must be escaped.
     *
     * @internal
     */
    protected function formatLabel(Definition $definition, string $withMetadata, array $options): string
    {
        $currentLabel = $options['label'] ?? '';

        if (!$withMetadata) {
            // Only currentLabel to handle. If null, will be translated to empty string
            return sprintf('"%s"', $this->escape($currentLabel));
        }
        $workflowMetadata = $definition->getMetadataStore()->getWorkflowMetadata();

        if ('' === $currentLabel) {
            // Only metadata to handle
            return sprintf('<%s>', $this->addMetadata($workflowMetadata, false));
        }

        // currentLabel and metadata to handle
        return sprintf('<<B>%s</B>%s>', $this->escape($currentLabel), $this->addMetadata($workflowMetadata));
    }

    private function addOptions(array $options): string
    {
        $code = [];

        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    /**
     * @param bool $lineBreakFirstIfNotEmpty Whether to add a separator in the first place when metadata is not empty
     */
    private function addMetadata(array $metadata, bool $lineBreakFirstIfNotEmpty = true): string
    {
        $code = [];

        $skipSeparator = !$lineBreakFirstIfNotEmpty;

        foreach ($metadata as $key => $value) {
            if ($skipSeparator) {
                $code[] = sprintf('%s: %s', $this->escape($key), $this->escape($value));
                $skipSeparator = false;
            } else {
                $code[] = sprintf('%s%s: %s', '<BR/>', $this->escape($key), $this->escape($value));
            }
        }

        return $code ? implode('', $code) : '';
    }
}
