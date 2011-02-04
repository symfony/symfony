<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

use Symfony\Component\DependencyInjection\Configuration\BaseNode;

use Symfony\Component\DependencyInjection\Configuration\BooleanNode;

use Symfony\Component\DependencyInjection\Configuration\ArrayNode;
use Symfony\Component\DependencyInjection\Configuration\ScalarNode;

/**
 * This is the entry class for building your own config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
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
        $method = 'create'.$node->type.'ConfigNode';
        if (!method_exists($this, $method)) {
            throw new \RuntimeException(sprintf('Unknown node type: "%s"', $node->type));
        }

        return $this->$method($node);
    }

    protected function createBooleanConfigNode(NodeBuilder $node)
    {
        $configNode = new BooleanNode($node->name, $node->parent);
        $this->configureScalarNode($configNode, $node);

        return $configNode;
    }

    protected function createScalarConfigNode(NodeBuilder $node)
    {
        $configNode = new ScalarNode($node->name, $node->parent);
        $this->configureScalarNode($configNode, $node);

        return $configNode;
    }

    protected function configureScalarNode(ScalarNode $configNode, NodeBuilder $node)
    {
        if (null !== $node->normalization) {
            $configNode->setNormalizationClosures(
                $this->buildExpressions($node->normalization->before)
            );
        }

        if (null !== $node->merge) {
            $configNode->setAllowOverwrite($node->merge->allowOverwrite);
        }

        if (true === $node->default) {
            $configNode->setDefaultValue($node->defaultValue);
        }

        if (false === $node->allowEmptyValue) {
            $configNode->setAllowEmptyValue($node->allowEmptyValue);
        }

        $configNode->addEquivalentValue(null, $node->nullEquivalent);
        $configNode->addEquivalentValue(true, $node->trueEquivalent);
        $configNode->addEquivalentValue(false, $node->falseEquivalent);
    }

    protected function createArrayConfigNode(NodeBuilder $node)
    {
        $configNode = new ArrayNode($node->name, $node->parent);
        $configNode->setAddIfNotSet($node->addDefaults);
        $configNode->setAllowNewKeys($node->allowNewKeys);
        $configNode->addEquivalentValue(null, $node->nullEquivalent);
        $configNode->addEquivalentValue(true, $node->trueEquivalent);
        $configNode->addEquivalentValue(false, $node->falseEquivalent);
        $configNode->setPerformDeepMerging($node->performDeepMerging);

        if (null !== $node->key) {
            $configNode->setKeyAttribute($node->key);
        }

        if (true === $node->atLeastOne) {
            $configNode->setMinNumberOfElements(1);
        }

        if (null !== $node->normalization) {
            $configNode->setNormalizationClosures(
                $this->buildExpressions($node->normalization->before)
            );

            $configNode->setXmlRemappings($node->normalization->remappings);
        }

        if (null !== $node->merge) {
            $configNode->setAllowOverwrite($node->merge->allowOverwrite);
            $configNode->setAllowFalse($node->merge->allowFalse);
        }

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