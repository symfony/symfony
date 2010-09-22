<?php

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * GraphvizDumper dumps a service container as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (http://www.graphviz.org/):
 *
 *   dot -Tpng container.dot > foo.png
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class GraphvizDumper extends Dumper
{
    protected $nodes;
    protected $edges;

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
     *
     * @param  array  $options An array of options
     *
     * @return string The dot representation of the service container
     */
    public function dump(array $options = array())
    {
        $this->options = array(
            'graph' => array('ratio' => 'compress'),
            'node'  => array('fontsize' => 11, 'fontname' => 'Arial', 'shape' => 'record'),
            'edge'  => array('fontsize' => 9, 'fontname' => 'Arial', 'color' => 'grey', 'arrowhead' => 'open', 'arrowsize' => 0.5),
            'node.instance' => array('fillcolor' => '#9999ff', 'style' => 'filled'),
            'node.definition' => array('fillcolor' => '#eeeeee'),
            'node.missing' => array('fillcolor' => '#ff9999', 'style' => 'filled'),
        );

        foreach (array('graph', 'node', 'edge', 'node.instance', 'node.definition', 'node.missing') as $key) {
            if (isset($options[$key])) {
                $this->options[$key] = array_merge($this->options[$key], $options[$key]);
            }
        }

        $this->nodes = $this->findNodes();

        $this->edges = array();
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $this->edges[$id] = $this->findEdges($id, $definition->getArguments(), true, '');

            foreach ($definition->getMethodCalls() as $call) {
                $this->edges[$id] = array_merge(
                    $this->edges[$id],
                    $this->findEdges($id, $call[1], false, $call[0].'()')
                );
            }
        }

        return $this->startDot().$this->addNodes().$this->addEdges().$this->endDot();
    }

    protected function addNodes()
    {
        $code = '';
        foreach ($this->nodes as $id => $node) {
            $aliases = $this->getAliases($id);

            $code .= sprintf("  node_%s [label=\"%s\\n%s\\n\", shape=%s%s];\n", $this->dotize($id), $id.($aliases ? ' ('.implode(', ', $aliases).')' : ''), $node['class'], $this->options['node']['shape'], $this->addAttributes($node['attributes']));
        }

        return $code;
    }

    protected function addEdges()
    {
        $code = '';
        foreach ($this->edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf("  node_%s -> node_%s [label=\"%s\" style=\"%s\"];\n", $this->dotize($id), $this->dotize($edge['to']), $edge['name'], $edge['required'] ? 'filled' : 'dashed');
            }
        }

        return $code;
    }

    protected function findEdges($id, $arguments, $required, $name)
    {
        $edges = array();
        foreach ($arguments as $argument) {
            if (is_object($argument) && $argument instanceof Parameter) {
                $argument = $this->container->hasParameter($argument) ? $this->container->getParameter($argument) : null;
            } elseif (is_string($argument) && preg_match('/^%([^%]+)%$/', $argument, $match)) {
                $argument = $this->container->hasParameter($match[1]) ? $this->container->getParameter($match[1]) : null;
            }

            if ($argument instanceof Reference) {
                if (!$this->container->has((string) $argument)) {
                    $this->nodes[(string) $argument] = array('name' => $name, 'required' => $required, 'class' => '', 'attributes' => $this->options['node.missing']);
                }

                $edges[] = array('name' => $name, 'required' => $required, 'to' => $argument);
            } elseif (is_array($argument)) {
                $edges = array_merge($edges, $this->findEdges($id, $argument, $required, $name));
            }
        }

        return $edges;
    }

    protected function findNodes()
    {
        $nodes = array();

        $container = clone $this->container;

        foreach ($container->getDefinitions() as $id => $definition) {
            $nodes[$id] = array('class' => str_replace('\\', '\\\\', $this->container->getParameterBag()->resolveValue($definition->getClass())), 'attributes' => array_merge($this->options['node.definition'], array('style' => $definition->isShared() ? 'filled' : 'dotted')));

            $container->setDefinition($id, new Definition('stdClass'));
        }

        foreach ($container->getServiceIds() as $id) {
            $service = $container->get($id);

            if (in_array($id, array_keys($container->getAliases()))) {
                continue;
            }

            if (!$container->hasDefinition($id)) {
                $nodes[$id] = array('class' => str_replace('\\', '\\\\', get_class($service)), 'attributes' => $this->options['node.instance']);
            }
        }

        return $nodes;
    }

    protected function startDot()
    {
        $parameters = var_export($this->container->getParameterBag()->all(), true);

        return sprintf("digraph sc {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($this->options['graph']),
            $this->addOptions($this->options['node']),
            $this->addOptions($this->options['edge'])
        );
    }

    protected function endDot()
    {
        return "}\n";
    }

    protected function addAttributes($attributes)
    {
        $code = array();
        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return $code ? ', '.implode(', ', $code) : '';
    }

    protected function addOptions($options)
    {
        $code = array();
        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    protected function dotize($id)
    {
        return strtolower(preg_replace('/[^\w]/i', '_', $id));
    }

    protected function getAliases($id)
    {
        $aliases = array();
        foreach ($this->container->getAliases() as $alias => $origin) {
            if ($id == $origin) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }
}
