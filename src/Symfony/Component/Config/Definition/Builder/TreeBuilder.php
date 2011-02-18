<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\BaseNode;

use Symfony\Component\Config\Definition\BooleanNode;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * This is the entry class for building your own config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TreeBuilder
{
    protected $root;
    protected $tree;

    /**
     * Creates the NodeBuilder for the root node
     *
     * @param string $name The name of the node
     * @param string $type The type of the node
     *
     * @return Symfony\Component\Config\Definition\Builder\NodeBuilder
     */
    public function root($name, $type)
    {
        $this->tree = null;

        return $this->root = new NodeBuilder($name, $type, $this);
    }

    /**
     * Builds the tree.
     *
     * @return Symfony\Component\Config\Definition\NodeInterface
     */
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

    /**
     * Creates a node.
     *
     * @param NodeBuilder $node The builder of the node
     *
     * @return Symfony\Component\Config\Definition\NodeInterface
     */
    protected function createConfigNode(NodeBuilder $node)
    {
        $method = 'create'.$node->type.'ConfigNode';
        if (!method_exists($this, $method)) {
            throw new \RuntimeException(sprintf('Unknown node type: "%s"', $node->type));
        }

        return $this->$method($node);
    }

    /**
     * Creates a boolean node.
     *
     * @param NodeBuilder $node The builder of the node
     *
     * @return Symfony\Component\Config\Definition\BooleanNode
     */
    protected function createBooleanConfigNode(NodeBuilder $node)
    {
        $configNode = new BooleanNode($node->name, $node->parent);
        $this->configureScalarNode($configNode, $node);

        return $configNode;
    }

    /**
     * Creates a scalar node.
     *
     * @param NodeBuilder $node the builder of the node
     *
     * @return Symfony\Component\Config\Definition\ScalarNode
     */
    protected function createScalarConfigNode(NodeBuilder $node)
    {
        $configNode = new ScalarNode($node->name, $node->parent);
        $this->configureScalarNode($configNode, $node);

        return $configNode;
    }

    /**
     * Configures a scalar node.
     *
     * @param ScalarNode  $configNode The node to configure
     * @param NodeBuilder $node       The builder of the node
     */
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
        $configNode->setRequired($node->required);

        if (null !== $node->validation) {
            $configNode->setFinalValidationClosures(
                $this->buildExpressions($node->validation->rules)
            );
        }
    }

    /**
     * Creates an array node.
     *
     * @param NodeBuilder $node The builder of the node
     *
     * @return Symfony\Component\Config\Definition\ArrayNode
     */
    protected function createArrayConfigNode(NodeBuilder $node)
    {
        $configNode = new ArrayNode($node->name, $node->parent);
        $configNode->setAddIfNotSet($node->addDefaults);
        $configNode->setAllowNewKeys($node->allowNewKeys);
        $configNode->addEquivalentValue(null, $node->nullEquivalent);
        $configNode->addEquivalentValue(true, $node->trueEquivalent);
        $configNode->addEquivalentValue(false, $node->falseEquivalent);
        $configNode->setPerformDeepMerging($node->performDeepMerging);
        $configNode->setRequired($node->required);
        $configNode->setPreventExtraKeys($node->preventExtraKeys);

        if (null !== $node->key) {
            $configNode->setKeyAttribute($node->key);
            $configNode->setKeyAttributeIsRemoved($node->removeKeyItem);
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

        if (null !== $node->defaultValue) {
            $configNode->setDefaultValue($node->defaultValue);
        }

        if (null !== $node->validation) {
            $configNode->setFinalValidationClosures(
                $this->buildExpressions($node->validation->rules)
            );
        }

        return $configNode;
    }

    /**
     * Builds the expressions.
     *
     * @param array $expressions An array of ExprBuilder instances to build
     *
     * @return array
     */
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