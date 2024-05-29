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

use Symfony\Component\Config\Definition\Exception\DuplicateKeyException;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\UnsetKeyException;

/**
 * Represents a prototyped Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PrototypedArrayNode extends ArrayNode
{
    protected PrototypeNodeInterface $prototype;
    protected ?string $keyAttribute = null;
    protected bool $removeKeyAttribute = false;
    protected int $minNumberOfElements = 0;
    protected array $defaultValue = [];
    protected ?array $defaultChildren = null;
    /**
     * @var NodeInterface[] An array of the prototypes of the simplified value children
     */
    private array $valuePrototypes = [];

    /**
     * Sets the minimum number of elements that a prototype based node must
     * contain. By default this is zero, meaning no elements.
     */
    public function setMinNumberOfElements(int $number): void
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
     *     [
     *         ['id' => 'my_name', 'foo' => 'bar'],
     *     ];
     *
     *  becomes
     *
     *      [
     *          'my_name' => ['foo' => 'bar'],
     *      ];
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * @param string $attribute The name of the attribute which value is to be used as a key
     * @param bool   $remove    Whether or not to remove the key
     */
    public function setKeyAttribute(string $attribute, bool $remove = true): void
    {
        $this->keyAttribute = $attribute;
        $this->removeKeyAttribute = $remove;
    }

    /**
     * Retrieves the name of the attribute which value should be used as key.
     */
    public function getKeyAttribute(): ?string
    {
        return $this->keyAttribute;
    }

    /**
     * Sets the default value of this node.
     */
    public function setDefaultValue(array $value): void
    {
        $this->defaultValue = $value;
    }

    public function hasDefaultValue(): bool
    {
        return true;
    }

    /**
     * Adds default children when none are set.
     *
     * @param int|string|array|null $children The number of children|The child name|The children names to be added
     */
    public function setAddChildrenIfNoneSet(int|string|array|null $children = ['defaults']): void
    {
        if (null === $children) {
            $this->defaultChildren = ['defaults'];
        } else {
            $this->defaultChildren = \is_int($children) && $children > 0 ? range(1, $children) : (array) $children;
        }
    }

    /**
     * The default value could be either explicited or derived from the prototype
     * default value.
     */
    public function getDefaultValue(): mixed
    {
        if (null !== $this->defaultChildren) {
            $default = $this->prototype->hasDefaultValue() ? $this->prototype->getDefaultValue() : [];
            $defaults = [];
            foreach (array_values($this->defaultChildren) as $i => $name) {
                $defaults[null === $this->keyAttribute ? $i : $name] = $default;
            }

            return $defaults;
        }

        return $this->defaultValue;
    }

    /**
     * Sets the node prototype.
     */
    public function setPrototype(PrototypeNodeInterface $node): void
    {
        $this->prototype = $node;
    }

    /**
     * Retrieves the prototype.
     */
    public function getPrototype(): PrototypeNodeInterface
    {
        return $this->prototype;
    }

    /**
     * Disable adding concrete children for prototyped nodes.
     *
     * @throws Exception
     */
    public function addChild(NodeInterface $node): never
    {
        throw new Exception('A prototyped array node cannot have concrete children.');
    }

    protected function finalizeValue(mixed $value): mixed
    {
        if (false === $value) {
            throw new UnsetKeyException(sprintf('Unsetting key for path "%s", value: %s.', $this->getPath(), json_encode($value)));
        }

        foreach ($value as $k => $v) {
            $prototype = $this->getPrototypeForChild($k);
            try {
                $value[$k] = $prototype->finalize($v);
            } catch (UnsetKeyException) {
                unset($value[$k]);
            }
        }

        if (\count($value) < $this->minNumberOfElements) {
            $ex = new InvalidConfigurationException(sprintf('The path "%s" should have at least %d element(s) defined.', $this->getPath(), $this->minNumberOfElements));
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }

    /**
     * @throws DuplicateKeyException
     */
    protected function normalizeValue(mixed $value): mixed
    {
        if (false === $value) {
            return $value;
        }

        $value = $this->remapXml($value);

        $isList = array_is_list($value);
        $normalized = [];
        foreach ($value as $k => $v) {
            if (null !== $this->keyAttribute && \is_array($v)) {
                if (!isset($v[$this->keyAttribute]) && \is_int($k) && $isList) {
                    $ex = new InvalidConfigurationException(sprintf('The attribute "%s" must be set for path "%s".', $this->keyAttribute, $this->getPath()));
                    $ex->setPath($this->getPath());

                    throw $ex;
                } elseif (isset($v[$this->keyAttribute])) {
                    $k = $v[$this->keyAttribute];

                    if (\is_float($k)) {
                        $k = var_export($k, true);
                    }

                    // remove the key attribute when required
                    if ($this->removeKeyAttribute) {
                        unset($v[$this->keyAttribute]);
                    }

                    // if only "value" is left
                    if (array_keys($v) === ['value']) {
                        $v = $v['value'];
                        if ($this->prototype instanceof ArrayNode && ($children = $this->prototype->getChildren()) && \array_key_exists('value', $children)) {
                            $valuePrototype = current($this->valuePrototypes) ?: clone $children['value'];
                            $valuePrototype->parent = $this;
                            $originalClosures = $this->prototype->normalizationClosures;
                            if (\is_array($originalClosures)) {
                                $valuePrototypeClosures = $valuePrototype->normalizationClosures;
                                $valuePrototype->normalizationClosures = \is_array($valuePrototypeClosures) ? array_merge($originalClosures, $valuePrototypeClosures) : $originalClosures;
                            }
                            $this->valuePrototypes[$k] = $valuePrototype;
                        }
                    }
                }

                if (\array_key_exists($k, $normalized)) {
                    $ex = new DuplicateKeyException(sprintf('Duplicate key "%s" for path "%s".', $k, $this->getPath()));
                    $ex->setPath($this->getPath());

                    throw $ex;
                }
            }

            $prototype = $this->getPrototypeForChild($k);
            if (null !== $this->keyAttribute || !$isList) {
                $normalized[$k] = $prototype->normalize($v);
            } else {
                $normalized[] = $prototype->normalize($v);
            }
        }

        return $normalized;
    }

    protected function mergeValues(mixed $leftSide, mixed $rightSide): mixed
    {
        if (false === $rightSide) {
            // if this is still false after the last config has been merged the
            // finalization pass will take care of removing this key entirely
            return false;
        }

        if (false === $leftSide || !$this->performDeepMerging) {
            return $rightSide;
        }

        $isList = array_is_list($rightSide);
        foreach ($rightSide as $k => $v) {
            // prototype, and key is irrelevant there are no named keys, append the element
            if (null === $this->keyAttribute && $isList) {
                $leftSide[] = $v;
                continue;
            }

            // no conflict
            if (!\array_key_exists($k, $leftSide)) {
                if (!$this->allowNewKeys) {
                    $ex = new InvalidConfigurationException(sprintf('You are not allowed to define new elements for path "%s". Please define all elements for this path in one config file.', $this->getPath()));
                    $ex->setPath($this->getPath());

                    throw $ex;
                }

                $leftSide[$k] = $v;
                continue;
            }

            $prototype = $this->getPrototypeForChild($k);
            $leftSide[$k] = $prototype->merge($leftSide[$k], $v);
        }

        return $leftSide;
    }

    /**
     * Returns a prototype for the child node that is associated to $key in the value array.
     * For general child nodes, this will be $this->prototype.
     * But if $this->removeKeyAttribute is true and there are only two keys in the child node:
     * one is same as this->keyAttribute and the other is 'value', then the prototype will be different.
     *
     * For example, assume $this->keyAttribute is 'name' and the value array is as follows:
     *
     *     [
     *         [
     *             'name' => 'name001',
     *             'value' => 'value001'
     *         ]
     *     ]
     *
     * Now, the key is 0 and the child node is:
     *
     *     [
     *        'name' => 'name001',
     *        'value' => 'value001'
     *     ]
     *
     * When normalizing the value array, the 'name' element will removed from the child node
     * and its value becomes the new key of the child node:
     *
     *     [
     *         'name001' => ['value' => 'value001']
     *     ]
     *
     * Now only 'value' element is left in the child node which can be further simplified into a string:
     *
     *     ['name001' => 'value001']
     *
     * Now, the key becomes 'name001' and the child node becomes 'value001' and
     * the prototype of child node 'name001' should be a ScalarNode instead of an ArrayNode instance.
     */
    private function getPrototypeForChild(string $key): mixed
    {
        $prototype = $this->valuePrototypes[$key] ?? $this->prototype;
        $prototype->setName($key);

        return $prototype;
    }
}
