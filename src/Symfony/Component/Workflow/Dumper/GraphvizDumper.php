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

/**
 * GraphvizDumper dumps a workflow as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (http://www.graphviz.org/):
 *
 *   dot -Tpng workflow.dot > workflow.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GraphvizDumper implements DumperInterface
{
    private $nodes;
    private $edges;
    private $options = array(
        'graph' => array('ratio' => 'compress', 'rankdir' => 'LR'),
        'node'  => array('fontsize' => 9, 'fontname' => 'Arial', 'color' => '#333', 'shape' => 'circle', 'fillcolor' => 'lightblue', 'fixedsize' => true, 'width' => 1),
        'edge'  => array('fontsize' => 9, 'fontname' => 'Arial', 'color' => '#333', 'arrowhead' => 'normal', 'arrowsize' => 0.5),
    );

    /**
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes
     *  * edge: The default options for edges
     *
     * @param Definition $definition A Definition instance
     * @param array      $options    An array of options
     *
     * @return string The dot representation of the workflow
     */
    public function dump(Definition $definition, array $options = array())
    {
        foreach (array('graph', 'node', 'edge') as $key) {
            if (isset($options[$key])) {
                $this->options[$key] = array_merge($this->options[$key], $options[$key]);
            }
        }

        $this->nodes = $this->findNodes($definition);
        $this->edges = $this->findEdges($definition);

        return $this->startDot().$this->addNodes().$this->addEdges().$this->endDot();
    }

    /**
     * Finds all nodes.
     *
     * @return array An array of all nodes
     */
    private function findNodes(Definition $definition)
    {
        $nodes = array();
        foreach ($definition->getStates() as $state) {
            $nodes[$state] = array(
                'attributes' => array_merge($this->options['node'], array('style' => $state == $definition->getInitialState() ? 'filled' : 'solid'))
            );
        }

        return $nodes;
    }

    /**
     * Returns all nodes.
     *
     * @return string A string representation of all nodes
     */
    private function addNodes()
    {
        $code = '';
        foreach ($this->nodes as $id => $node) {
            $code .= sprintf("  node_%s [label=\"%s\", shape=%s%s];\n", $this->dotize($id), $id, $this->options['node']['shape'], $this->addAttributes($node['attributes']));
        }

        return $code;
    }

    private function findEdges(Definition $definition)
    {
        $edges = array();
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFroms() as $from) {
                foreach ($transition->getTos() as $to) {
                    $edges[$from][] = array(
                        'name' => $transition->getName(),
                        'to' => $to,
                    );
                }
            }
        }

        return $edges;
    }

    /**
     * Returns all edges.
     *
     * @return string A string representation of all edges
     */
    private function addEdges()
    {
        $code = '';
        foreach ($this->edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf("  node_%s -> node_%s [label=\"%s\" style=\"%s\"];\n", $this->dotize($id), $this->dotize($edge['to']), $edge['name'], 'solid');
            }
        }

        return $code;
    }

    /**
     * Returns the start dot.
     *
     * @return string The string representation of a start dot
     */
    private function startDot()
    {
        return sprintf("digraph workflow {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($this->options['graph']),
            $this->addOptions($this->options['node']),
            $this->addOptions($this->options['edge'])
        );
    }

    /**
     * Returns the end dot.
     *
     * @return string
     */
    private function endDot()
    {
        return "}\n";
    }

    /**
     * Adds attributes
     *
     * @param array $attributes An array of attributes
     *
     * @return string A comma separated list of attributes
     */
    private function addAttributes($attributes)
    {
        $code = array();
        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return $code ? ', '.implode(', ', $code) : '';
    }

    /**
     * Adds options
     *
     * @param array $options An array of options
     *
     * @return string A space separated list of options
     */
    private function addOptions($options)
    {
        $code = array();
        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    /**
     * Dotizes an identifier.
     *
     * @param string $id The identifier to dotize
     *
     * @return string A dotized string
     */
    private function dotize($id)
    {
        return strtolower(preg_replace('/[^\w]/i', '_', $id));
    }
}
