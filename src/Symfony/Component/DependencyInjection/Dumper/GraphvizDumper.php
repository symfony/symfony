<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

/**
 * GraphvizDumper dumps a service container as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (http://www.graphviz.org/):
 *
 *   dot -Tpng container.dot > foo.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GraphvizDumper extends Dumper
{
    private array $nodes;
    private array $edges;
    // All values should be strings
    private array $options = [
            'graph' => ['ratio' => 'compress'],
            'node' => ['fontsize' => '11', 'fontname' => 'Arial', 'shape' => 'record'],
            'edge' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => 'grey', 'arrowhead' => 'open', 'arrowsize' => '0.5'],
            'node.instance' => ['fillcolor' => '#9999ff', 'style' => 'filled'],
            'node.definition' => ['fillcolor' => '#eeeeee'],
            'node.missing' => ['fillcolor' => '#ff9999', 'style' => 'filled'],
        ];

    /**
     * Dumps the service container as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes
     *  * edge: The default options for edges
     *  * node.instance: The default options for services that are defined directly by object instances
     *  * node.definition: The default options for services that are defined via service definition instances
     *  * node.missing: The default options for missing services
     */
    public function dump(array $options = []): string
    {
        foreach (['graph', 'node', 'edge', 'node.instance', 'node.definition', 'node.missing'] as $key) {
            if (isset($options[$key])) {
                $this->options[$key] = array_merge($this->options[$key], $options[$key]);
            }
        }

        $this->nodes = $this->findNodes();

        $this->edges = [];
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $this->edges[$id] = array_merge(
                $this->findEdges($id, $definition->getArguments(), true, ''),
                $this->findEdges($id, $definition->getProperties(), false, '')
            );

            foreach ($definition->getMethodCalls() as $call) {
                $this->edges[$id] = array_merge(
                    $this->edges[$id],
                    $this->findEdges($id, $call[1], false, $call[0].'()')
                );
            }
        }

        return $this->container->resolveEnvPlaceholders($this->startDot().$this->addNodes().$this->addEdges().$this->endDot(), '__ENV_%s__');
    }

    private function addNodes(): string
    {
        $code = '';
        foreach ($this->nodes as $id => $node) {
            $aliases = $this->getAliases($id);

            $code .= sprintf("  node_%s [label=\"%s\\n%s\\n\", shape=%s%s];\n", $this->dotize($id), $id.($aliases ? ' ('.implode(', ', $aliases).')' : ''), $node['class'], $this->options['node']['shape'], $this->addAttributes($node['attributes']));
        }

        return $code;
    }

    private function addEdges(): string
    {
        $code = '';
        foreach ($this->edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf("  node_%s -> node_%s [label=\"%s\" style=\"%s\"%s];\n", $this->dotize($id), $this->dotize($edge['to']), $edge['name'], $edge['required'] ? 'filled' : 'dashed', $edge['lazy'] ? ' color="#9999ff"' : '');
            }
        }

        return $code;
    }

    /**
     * Finds all edges belonging to a specific service id.
     */
    private function findEdges(string $id, array $arguments, bool $required, string $name, bool $lazy = false): array
    {
        $edges = [];
        foreach ($arguments as $argument) {
            if ($argument instanceof Parameter) {
                $argument = $this->container->hasParameter($argument) ? $this->container->getParameter($argument) : null;
            } elseif (\is_string($argument) && preg_match('/^%([^%]+)%$/', $argument, $match)) {
                $argument = $this->container->hasParameter($match[1]) ? $this->container->getParameter($match[1]) : null;
            }

            if ($argument instanceof Reference) {
                $lazyEdge = $lazy;

                if (!$this->container->has((string) $argument)) {
                    $this->nodes[(string) $argument] = ['name' => $name, 'required' => $required, 'class' => '', 'attributes' => $this->options['node.missing']];
                } elseif ('service_container' !== (string) $argument) {
                    $lazyEdge = $lazy || $this->container->getDefinition((string) $argument)->isLazy();
                }

                $edges[] = [['name' => $name, 'required' => $required, 'to' => $argument, 'lazy' => $lazyEdge]];
            } elseif ($argument instanceof ArgumentInterface) {
                $edges[] = $this->findEdges($id, $argument->getValues(), $required, $name, true);
            } elseif ($argument instanceof Definition) {
                $edges[] = $this->findEdges($id, $argument->getArguments(), $required, '');
                $edges[] = $this->findEdges($id, $argument->getProperties(), false, '');

                foreach ($argument->getMethodCalls() as $call) {
                    $edges[] = $this->findEdges($id, $call[1], false, $call[0].'()');
                }
            } elseif (\is_array($argument)) {
                $edges[] = $this->findEdges($id, $argument, $required, $name, $lazy);
            }
        }

        return array_merge([], ...$edges);
    }

    private function findNodes(): array
    {
        $nodes = [];

        $container = $this->cloneContainer();

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if ('\\' === substr($class, 0, 1)) {
                $class = substr($class, 1);
            }

            try {
                $class = $this->container->getParameterBag()->resolveValue($class);
            } catch (ParameterNotFoundException $e) {
            }

            $nodes[$id] = ['class' => str_replace('\\', '\\\\', $class), 'attributes' => array_merge($this->options['node.definition'], ['style' => $definition->isShared() ? 'filled' : 'dotted'])];
            $container->setDefinition($id, new Definition('stdClass'));
        }

        foreach ($container->getServiceIds() as $id) {
            if (\array_key_exists($id, $container->getAliases())) {
                continue;
            }

            if (!$container->hasDefinition($id)) {
                $nodes[$id] = ['class' => str_replace('\\', '\\\\', \get_class($container->get($id))), 'attributes' => $this->options['node.instance']];
            }
        }

        return $nodes;
    }

    private function cloneContainer(): ContainerBuilder
    {
        $parameterBag = new ParameterBag($this->container->getParameterBag()->all());

        $container = new ContainerBuilder($parameterBag);
        $container->setDefinitions($this->container->getDefinitions());
        $container->setAliases($this->container->getAliases());
        $container->setResources($this->container->getResources());
        foreach ($this->container->getExtensions() as $extension) {
            $container->registerExtension($extension);
        }

        return $container;
    }

    private function startDot(): string
    {
        return sprintf("digraph sc {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($this->options['graph']),
            $this->addOptions($this->options['node']),
            $this->addOptions($this->options['edge'])
        );
    }

    private function endDot(): string
    {
        return "}\n";
    }

    private function addAttributes(array $attributes): string
    {
        $code = [];
        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return $code ? ', '.implode(', ', $code) : '';
    }

    private function addOptions(array $options): string
    {
        $code = [];
        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    private function dotize(string $id): string
    {
        return preg_replace('/\W/i', '_', $id);
    }

    private function getAliases(string $id): array
    {
        $aliases = [];
        foreach ($this->container->getAliases() as $alias => $origin) {
            if ($id == $origin) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }
}
