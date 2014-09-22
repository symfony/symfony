<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\DuplicateKeyException;
use Symfony\Component\Config\Definition\Exception\UnsetKeyException;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Represents a prototyped Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PrototypedArrayNode extends ArrayNode
{
    protected $prototype;
    protected $keyAttribute;
    protected $removeKeyAttribute = false;
    protected $minNumberOfElements = 0;
    protected $defaultValue = array();
    protected $defaultChildren;

    /**
     * Sets the minimum number of elements that a prototype based node must
     * contain. By default this is zero, meaning no elements.
     *
     * @param int     $number
     */
    public function setMinNumberOfElements($number)
    {
        $this->minNumberOfElements = $number;
    }

    /**
     * Sets the attribute which value is to be used as key.
     *
     * This is useful when you have an indexed array that should be an
     * associative array. You can select an item from within the array
     * to be the key of the particular item. For example, if "id" is the
     * "key", then:
     *
     *     array(
     *         array('id' => 'my_name', 'foo' => 'bar'),
     *     );
     *
     *  becomes
     *
     *      array(
     *          'my_name' => array('foo' => 'bar'),
     *      );
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * @param string  $attribute The name of the attribute which value is to be used as a key
     * @param bool    $remove    Whether or not to remove the key
     */
    public function setKeyAttribute($attribute, $remove = true)
    {
        $this->keyAttribute = $attribute;
        $this->removeKeyAttribute = $remove;
    }

    /**
     * Retrieves the name of the attribute which value should be used as key.
     *
     * @return string The name of the attribute
     */
    public function getKeyAttribute()
    {
        return $this->keyAttribute;
    }

    /**
     * Sets the default value of this node.
     *
     * @param string $value
     *
     * @throws \InvalidArgumentException if the default value is not an array
     */
    public function setDefaultValue($value)
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($this->getPath().': the default value of an array node has to be an array.');
        }

        $this->defaultValue = $value;
    }

    /**
     * Checks if the node has a default value.
     *
     * @return bool
     */
    public function hasDefaultValue()
    {
        return true;
    }

    /**
     * Adds default children when none are set.
     *
     * @param int|string|array|null     $children The number of children|The child name|The children names to be added
     */
    public function setAddChildrenIfNoneSet($children = array('defaults'))
    {
        if (null === $children) {
            $this->defaultChildren = array('defaults');
        } else {
            $this->defaultChildren = is_integer($children) && $children > 0 ? range(1, $children) : (array) $children;
        }
    }

    /**
     * Retrieves the default value.
     *
     * The default value could be either explicited or derived from the prototype
     * default value.
     *
     * @return array The default value
     */
    public function getDefaultValue()
    {
        if (null !== $this->defaultChildren) {
            $default = $this->prototype->hasDefaultValue() ? $this->prototype->getDefaultValue() : array();
            $defaults = array();
            foreach (array_values($this->defaultChildren) as $i => $name) {
                $defaults[null === $this->keyAttribute ? $i : $name] = $default;
            }

            return $defaults;
        }

        return $this->defaultValue;
    }

    /**
     * Sets the node prototype.
     *
     * @param PrototypeNodeInterface $node
     */
    public function setPrototype(PrototypeNodeInterface $node)
    {
        $this->prototype = $node;
    }

    /**
     * Retrieves the prototype
     *
     * @return PrototypeNodeInterface The prototype
     */
    public function getPrototype()
    {
        return $this->prototype;
    }

    /**
     * Disable adding concrete children for prototyped nodes.
     *
     * @param NodeInterface $node The child node to add
     *
     * @throws Exception
     */
    public function addChild(NodeInterface $node)
    {
        throw new Exception('A prototyped array node can not have concrete children.');
    }

    /**
     * Finalizes the value of this node.
     *
     * @param mixed $value
     *
     * @return mixed The finalized value
     *
     * @throws UnsetKeyException
     * @throws InvalidConfigurationException if the node doesn't have enough children
     */
    protected function finalizeValue($value)
    {
        if (false === $value) {
            $msg = sprintf('Unsetting key for path "%s", value: %s', $this->getPath(), json_encode($value));
            throw new UnsetKeyException($msg);
        }

        foreach ($value as $k => $v) {
            $this->prototype->setName($k);
            try {
                $value[$k] = $this->prototype->finalize($v);
            } catch (UnsetKeyException $unset) {
                unset($value[$k]);
            }
        }

        if (count($value) < $this->minNumberOfElements) {
            $msg = sprintf('The path "%s" should have at least %d element(s) defined.', $this->getPath(), $this->minNumberOfElements);
            $ex = new InvalidConfigurationException($msg);
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }

    /**
     * Normalizes the value.
     *
     * @param mixed $value The value to normalize
     *
     * @return mixed The normalized value
     *
     * @throws InvalidConfigurationException
     * @throws DuplicateKeyException
     */
    protected function normalizeValue($value)
    {
        if (false === $value) {
            return $value;
        }

        $value = $this->remapXml($value);

        $isAssoc = array_keys($value) !== range(0, count($value) -1);
        $normalized = array();
        foreach ($value as $k => $v) {
            if (null !== $this->keyAttribute && is_array($v)) {
                if (!isset($v[$this->keyAttribute]) && is_int($k) && !$isAssoc) {
                    $msg = sprintf('The attribute "%s" must be set for path "%s".', $this->keyAttribute, $this->getPath());
                    $ex = new InvalidConfigurationException($msg);
                    $ex->setPath($this->getPath());

                    throw $ex;
                } elseif (isset($v[$this->keyAttribute])) {
                    $k = $v[$this->keyAttribute];

                    // remove the key attribute when required
                    if ($this->removeKeyAttribute) {
                        unset($v[$this->keyAttribute]);
                    }

                    // if only "value" is left
                    if (1 == count($v) && isset($v['value'])) {
                        $v = $v['value'];
                    }
                }

                if (array_key_exists($k, $normalized)) {
                    $msg = sprintf('Duplicate key "%s" for path "%s".', $k, $this->getPath());
                    $ex = new DuplicateKeyException($msg);
                    $ex->setPath($this->getPath());

                    throw $ex;
                }
            }

            $this->prototype->setName($k);
            if (null !== $this->keyAttribute || $isAssoc) {
                $normalized[$k] = $this->prototype->normalize($v);
            } else {
                $normalized[] = $this->prototype->normalize($v);
            }
        }

        return $normalized;
    }

    /**
     * Merges values together.
     *
     * @param mixed $leftSide  The left side to merge.
     * @param mixed $rightSide The right side to merge.
     *
     * @return mixed The merged values
     *
     * @throws InvalidConfigurationException
     * @throws \RuntimeException
     */
    protected function mergeValues($leftSide, $rightSide)
    {
        if (false === $rightSide) {
            // if this is still false after the last config has been merged the
            // finalization pass will take care of removing this key entirely
            return false;
        }

        if (false === $leftSide || !$this->performDeepMerging) {
            return $rightSide;
        }

        foreach ($rightSide as $k => $v) {
            // prototype, and key is irrelevant, so simply append the element
            if (null === $this->keyAttribute) {
                $leftSide[] = $v;
                continue;
            }

            // no conflict
            if (!array_key_exists($k, $leftSide)) {
                if (!$this->allowNewKeys) {
                    $ex = new InvalidConfigurationException(sprintf(
                        'You are not allowed to define new elements for path "%s". '.
                        'Please define all elements for this path in one config file.',
                        $this->getPath()
                    ));
                    $ex->setPath($this->getPath());

                    throw $ex;
                }

                $leftSide[$k] = $v;
                continue;
            }

            $this->prototype->setName($k);
            $leftSide[$k] = $this->prototype->merge($leftSide[$k], $v);
        }

        return $leftSide;
    }
}
