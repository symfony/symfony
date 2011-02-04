<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class provides a fluent interface for building a config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NodeBuilder
{
    /************
     * READ-ONLY
     ************/
    public $name;
    public $type;
    public $key;
    public $parent;
    public $children;
    public $prototype;
    public $normalization;
    public $merge;
    public $finalization;
    public $defaultValue;
    public $default;
    public $addDefaults;
    public $required;
    public $atLeastOne;
    public $allowNewKeys;
    public $allowEmptyValue;
    public $nullEquivalent;
    public $trueEquivalent;
    public $falseEquivalent;
    public $performDeepMerging;

    public function __construct($name, $type, $parent = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->parent = $parent;

        $this->default = false;
        $this->required = false;
        $this->addDefaults = false;
        $this->allowNewKeys = true;
        $this->atLeastOne = false;
        $this->allowEmptyValue = true;
        $this->children = array();
        $this->performDeepMerging = true;

        if ('boolean' === $type) {
            $this->nullEquivalent = true;
        } else if ('array' === $type) {
            $this->nullEquivalent = array();
        }

        if ('array' === $type) {
            $this->trueEquivalent = array();
        } else {
            $this->trueEquivalent = true;
        }

        $this->falseEquivalent = false;
    }

    /****************************
     * FLUID INTERFACE
     ****************************/

    public function node($name, $type)
    {
        $node = new static($name, $type, $this);

        return $this->children[$name] = $node;
    }

    public function arrayNode($name)
    {
        return $this->node($name, 'array');
    }

    public function scalarNode($name)
    {
        return $this->node($name, 'scalar');
    }

    public function booleanNode($name)
    {
        return $this->node($name, 'boolean');
    }

    public function defaultValue($value)
    {
        $this->default = true;
        $this->defaultValue = $value;

        return $this;
    }

    public function isRequired()
    {
        $this->required = true;

        return $this;
    }

    public function containsNameValuePairsWithKeyAttribute($attribute)
    {
        $this->beforeNormalization()
                ->ifArray()
                ->thenReplaceKeyWithAttribute($attribute)
        ;

        $this->useAttributeAsKey($attribute);

        return $this;
    }

    public function requiresAtLeastOneElement()
    {
        $this->atLeastOne = true;

        return $this;
    }

    public function treatNullLike($value)
    {
        $this->nullEquivalent = $value;

        return $this;
    }

    public function treatTrueLike($value)
    {
        $this->trueEquivalent = $value;

        return $this;
    }

    public function treatFalseLike($value)
    {
        $this->falseEquivalent = $value;

        return $this;
    }

    public function defaultNull()
    {
        return $this->defaultValue(null);
    }

    public function defaultTrue()
    {
        return $this->defaultValue(true);
    }

    public function defaultFalse()
    {
        return $this->defaultValue(false);
    }

    public function addDefaultsIfNotSet()
    {
        $this->addDefaults = true;

        return $this;
    }

    public function disallowNewKeysInSubsequentConfigs()
    {
        $this->allowNewKeys = false;

        return $this;
    }

    protected function normalization()
    {
        if (null === $this->normalization) {
            $this->normalization = new NormalizationBuilder($this);
        }

        return $this->normalization;
    }

    public function beforeNormalization()
    {
        return $this->normalization()->before();
    }

    public function fixXmlConfig($singular, $plural = null)
    {
        $this->normalization()->remap($singular, $plural);

        return $this;
    }

    public function useAttributeAsKey($name)
    {
        $this->key = $name;

        return $this;
    }

    protected function merge()
    {
        if (null === $this->merge) {
            $this->merge = new MergeBuilder($this);
        }

        return $this->merge;
    }

    public function cannotBeOverwritten($deny = true)
    {
        $this->merge()->denyOverwrite($deny);

        return $this;
    }

    public function cannotBeEmpty()
    {
        $this->allowEmptyValue = false;

        return $this;
    }

    public function canBeUnset($allow = true)
    {
        $this->merge()->allowUnset($allow);

        return $this;
    }

    public function prototype($type)
    {
        return $this->prototype = new static(null, $type, $this);
    }

    public function performNoDeepMerging()
    {
        $this->performDeepMerging = false;

        return $this;
    }

    public function end()
    {
        return $this->parent;
    }
}