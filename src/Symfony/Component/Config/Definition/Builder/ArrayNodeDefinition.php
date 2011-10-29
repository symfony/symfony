<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * This class provides a fluent interface for defining an array node.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ArrayNodeDefinition extends NodeDefinition implements ParentNodeDefinitionInterface
{
    protected $performDeepMerging;
    protected $ignoreExtraKeys;
    protected $children;
    protected $prototype;
    protected $atLeastOne;
    protected $allowNewKeys;
    protected $key;
    protected $removeKeyItem;
    protected $addDefaults;
    protected $nodeBuilder;

    /**
     * {@inheritDoc}
     */
    public function __construct($name, NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->children = array();
        $this->addDefaults = false;
        $this->allowNewKeys = true;
        $this->atLeastOne = false;
        $this->allowEmptyValue = true;
        $this->performDeepMerging = true;
        $this->nullEquivalent = array();
        $this->trueEquivalent = array();
    }

    /**
     * Set a custom children builder
     *
     * @param NodeBuilder $builder A custom NodeBuilder
     */
    public function setBuilder(NodeBuilder $builder)
    {
        $this->nodeBuilder = $builder;
    }

    /**
     * Returns a builder to add children nodes
     *
     * @return NodeBuilder
     */
    public function children()
    {
        return $this->getNodeBuilder();
    }

    /**
     * Sets a prototype for child nodes.
     *
     * @param string $type the type of node
     *
     * @return NodeDefinition
     */
    public function prototype($type)
    {
        $builder = $this->getNodeBuilder();
        $this->prototype = $builder->node(null, $type);
        $this->prototype->parent = $this;

        return $this->prototype;
    }

    /**
     * Adds the default value if the node is not set in the configuration.
     *
     * @return ArrayNodeDefinition
     */
    public function addDefaultsIfNotSet()
    {
        $this->addDefaults = true;

        return $this;
    }

    /**
     * Requires the node to have at least one element.
     *
     * @return ArrayNodeDefinition
     */
    public function requiresAtLeastOneElement()
    {
        $this->atLeastOne = true;

        return $this;
    }

    /**
     * Disallows adding news keys in a subsequent configuration.
     *
     * If used all keys have to be defined in the same configuration file.
     *
     * @return ArrayNodeDefinition
     */
    public function disallowNewKeysInSubsequentConfigs()
    {
        $this->allowNewKeys = false;

        return $this;
    }

    /**
     * Sets a normalization rule for XML configurations.
     *
     * @param string $singular The key to remap
     * @param string $plural   The plural of the key for irregular plurals
     *
     * @return ArrayNodeDefinition
     */
    public function fixXmlConfig($singular, $plural = null)
    {
        $this->normalization()->remap($singular, $plural);

        return $this;
    }

    /**
     * Set the attribute which value is to be used as key.
     *
     * This is useful when you have an indexed array that should be an
     * associative array. You can select an item from within the array
     * to be the key of the particular item. For example, if "id" is the
     * "key", then:
     *
     *     array(
     *         array('id' => 'my_name', 'foo' => 'bar'),
     *     )
     *
     * becomes
     *
     *     array(
     *         'my_name' => array('foo' => 'bar'),
     *     )
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * @param string  $name          The name of the key
     * @param Boolean $removeKeyItem Whether or not the key item should be removed.
     *
     * @return ArrayNodeDefinition
     */
    public function useAttributeAsKey($name, $removeKeyItem = true)
    {
        $this->key = $name;
        $this->removeKeyItem = $removeKeyItem;

        return $this;
    }

    /**
     * Sets whether the node can be unset.
     *
     * @param Boolean $allow
     *
     * @return ArrayNodeDefinition
     */
    public function canBeUnset($allow = true)
    {
        $this->merge()->allowUnset($allow);

        return $this;
    }

    /**
     * Disables the deep merging of the node.
     *
     * @return ArrayNodeDefinition
     */
    public function performNoDeepMerging()
    {
        $this->performDeepMerging = false;

        return $this;
    }

    /**
     * Allows extra config keys to be specified under an array without
     * throwing an exception.
     *
     * Those config values are simply ignored. This should be used only
     * in special cases where you want to send an entire configuration
     * array through a special tree that processes only part of the array.
     *
     * @return ArrayNodeDefinition
     */
    public function ignoreExtraKeys()
    {
        $this->ignoreExtraKeys = true;

        return $this;
    }

    /**
     * Append a node definition.
     *
     *     $node = new ArrayNodeDefinition()
     *         ->children()
     *             ->scalarNode('foo')
     *             ->scalarNode('baz')
     *         ->end()
     *         ->append($this->getBarNodeDefinition())
     *     ;
     *
     * @return ArrayNodeDefinition This node
     */
    public function append(NodeDefinition $node)
    {
        $this->children[$node->name] = $node->setParent($this);

        return $this;
    }

    /**
     * Returns a node builder to be used to add children and prototype
     *
     * @return NodeBuilder The node builder
     */
    protected function getNodeBuilder()
    {
        if (null === $this->nodeBuilder) {
            $this->nodeBuilder = new NodeBuilder();
        }

        return $this->nodeBuilder->setParent($this);
    }

    /**
     * {@inheritDoc}
     */
    protected function createNode()
    {
        if (null == $this->prototype) {
            $node = new ArrayNode($this->name, $this->parent);
        } else {
            $node = new PrototypedArrayNode($this->name, $this->parent);
        }

        $node->setAddIfNotSet($this->addDefaults);
        $node->setAllowNewKeys($this->allowNewKeys);
        $node->addEquivalentValue(null, $this->nullEquivalent);
        $node->addEquivalentValue(true, $this->trueEquivalent);
        $node->addEquivalentValue(false, $this->falseEquivalent);
        $node->setPerformDeepMerging($this->performDeepMerging);
        $node->setRequired($this->required);
        $node->setIgnoreExtraKeys($this->ignoreExtraKeys);

        if (null !== $this->normalization) {
            $node->setNormalizationClosures($this->normalization->before);
            $node->setXmlRemappings($this->normalization->remappings);
        }

        if (null !== $this->merge) {
            $node->setAllowOverwrite($this->merge->allowOverwrite);
            $node->setAllowFalse($this->merge->allowFalse);
        }

        if (null !== $this->validation) {
            $node->setFinalValidationClosures($this->validation->rules);
        }

        if (null == $this->prototype) {
            foreach ($this->children as $child) {
                $child->parent = $node;
                $node->addChild($child->getNode());
            }
        } else {
            if (null !== $this->key) {
                $node->setKeyAttribute($this->key, $this->removeKeyItem);
            }

            if (true === $this->atLeastOne) {
                $node->setMinNumberOfElements(1);
            }

            if (null !== $this->defaultValue) {
                $node->setDefaultValue($this->defaultValue);
            }

            $this->prototype->parent = $node;
            $node->setPrototype($this->prototype->getNode());
        }

        return $node;
    }

}
