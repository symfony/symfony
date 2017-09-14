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

class StateMachineGraphvizDumper extends GraphvizDumper
{
    /**
     * {@inheritdoc}
     *
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes (places)
     *  * edge: The default options for edges
     */
    public function dump(Definition $definition, Marking $marking = null, array $options = array())
    {
        $places = $this->findPlaces($definition, $marking);
        $edges = $this->findEdges($definition);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        return $this->startDot($options)
            .$this->addPlaces($places)
            .$this->addEdges($edges)
            .$this->endDot()
        ;
    }

    /**
     * @internal
     */
    protected function findEdges(Definition $definition)
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
     * @internal
     */
    protected function addEdges(array $edges)
    {
        $code = '';

        foreach ($edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf("  place_%s -> place_%s [label=\"%s\" style=\"%s\"];\n", $this->dotize($id), $this->dotize($edge['to']), $edge['name'], 'solid');
            }
        }

        return $code;
    }
}
