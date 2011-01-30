<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

use Symfony\Component\DependencyInjection\Configuration\ArrayNode;
use Symfony\Component\DependencyInjection\Configuration\ScalarNode;

class TreeBuilder
{
    protected $root;
    protected $tree;

    public function root($name, $type)
    {
        $this->tree = null;

        return $this->root = new NodeBuilder($name, $type, $this);
    }

    public function buildTree()
    {
        if (null === $this->root) {
            throw new \RuntimeException('You haven\'t added a root node.');
        }
        if (null !== $this->tree) {
            return $this->tree;
        }
        $this->root->parent = null;

        return $this->tree = $this->createConfigNode($this->root);
    }

    protected function createConfigNode(NodeBuilder $node)
    {
        $node->beforeTransformations = $this->buildExpressions($node->beforeTransformations);
        $node->afterTransformations = $this->buildExpressions($node->afterTransformations);

        $method = 'create'.$node->type.'ConfigNode';
        if (!method_exists($this, $method)) {
            throw new \RuntimeException(sprintf('Unknown node type: "%s"', $node->type));
        }

        return $this->$method($node);
    }

    protected function createScalarConfigNode(NodeBuilder $node)
    {
        return new ScalarNode($node->name, $node->parent, $node->beforeTransformations, $node->afterTransformations);
    }

    protected function createArrayConfigNode(NodeBuilder $node)
    {
        $configNode = new ArrayNode($node->name, $node->parent, $node->beforeTransformations, $node->afterTransformations, $node->normalizeTransformations, $node->key);

        foreach ($node->children as $child) {
            $child->parent = $configNode;

            $configNode->addChild($this->createConfigNode($child));
        }

        if (null !== $node->prototype) {
            $node->prototype->parent = $configNode;
            $configNode->setPrototype($this->createConfigNode($node->prototype));
        }

        return $configNode;
    }

    protected function buildExpressions(array $expressions)
    {
        foreach ($expressions as $k => $expr) {
            if (!$expr instanceof ExprBuilder) {
                continue;
            }

            $expressions[$k] = function($v) use($expr) {
                $ifPart = $expr->ifPart;
                if (true !== $ifPart($v)) {
                    return $v;
                }

                $thenPart = $expr->thenPart;

                return $thenPart($v);
            };
        }

        return $expressions;
    }
}