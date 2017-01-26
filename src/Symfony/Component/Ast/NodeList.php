<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ast;

use PhpParser\Node;

final class NodeList
{
    private $nodes = array();

    /**
     * @param Node[] $nodes
     */
    public function __construct(array $nodes)
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * @return Node[]
     */
    public function getNodes()
    {
        return $this->nodes;
    }

    public function addNode(Node $node)
    {
        $this->nodes[] = $node;
    }

    public function append(NodeList $nodeList)
    {
        foreach ($nodeList->getNodes() as $node) {
            $this->addNode($node);
        }
    }
}
