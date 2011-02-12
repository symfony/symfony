<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Configuration\Exception\DuplicateKeyException;
use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidTypeException;
use Symfony\Component\DependencyInjection\Configuration\Exception\UnsetKeyException;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Represents an ARRAY node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ArrayNode extends BaseNode implements PrototypeNodeInterface
{
    protected $xmlRemappings;
    protected $children;
    protected $prototype;
    protected $keyAttribute;
    protected $allowFalse;
    protected $allowNewKeys;
    protected $addIfNotSet;
    protected $minNumberOfElements;
    protected $performDeepMerging;
    protected $defaultValue;

    public function __construct($name, NodeInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->children = array();
        $this->xmlRemappings = array();
        $this->allowFalse = false;
        $this->addIfNotSet = false;
        $this->allowNewKeys = true;
        $this->performDeepMerging = true;
        $this->minNumberOfElements = 0;
    }

    /**
     * Sets the xml remappings that should be performed.
     *
     * @param array $remappings an array of the form array(array(string, string))
     * @return void
     */
    public function setXmlRemappings(array $remappings)
    {
        $this->xmlRemappings = $remappings;
    }

    /**
     * Sets the minimum number of elements that a prototype based node must
     * contain. By default this is zero, meaning no elements.
     *
     * @param integer $number
     * @return void
     */
    public function setMinNumberOfElements($number)
    {
        $this->minNumberOfElements = $number;
    }

    /**
     * The name of the attribute that should be used as key.
     *
     * This is only relevant for XML configurations, and only in combination
     * with a prototype based node.
     *
     * @param string $attribute
     * @return void
     */
    public function setKeyAttribute($attribute)
    {
        $this->keyAttribute = $attribute;
    }

    /**
     * Sets whether to add default values for this array if it has not been
     * defined in any of the configuration files.
     *
     * @param Boolean $boolean
     * @return void
     */
    public function setAddIfNotSet($boolean)
    {
        $this->addIfNotSet = (Boolean) $boolean;
    }

    /**
     * Sets whether false is allowed as value indicating that the array should
     * be unset.
     *
     * @param Boolean $allow
     * @return void
     */
    public function setAllowFalse($allow)
    {
        $this->allowFalse = (Boolean) $allow;
    }

    /**
     * Sets whether new keys can be defined in subsequent configurations.
     *
     * @param Boolean $allow
     * @return void
     */
    public function setAllowNewKeys($allow)
    {
        $this->allowNewKeys = (Boolean) $allow;
    }

    public function setPerformDeepMerging($boolean)
    {
        $this->performDeepMerging = (Boolean) $boolean;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setDefaultValue($value)
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($this->getPath().': the default value of an array node has to be an array.');
        }
        $this->defaultValue = $value;
    }

    public function hasDefaultValue()
    {
        if (null !== $this->prototype) {
            return true;
        }

        return $this->addIfNotSet;
    }

    public function getDefaultValue()
    {
        if (!$this->hasDefaultValue()) {
            throw new \RuntimeException(sprintf('The node at path "%s" has no default value.', $this->getPath()));
        }

        if (null !== $this->prototype) {
            return $this->defaultValue ?: array();
        }

        if (null !== $this->defaultValue) {
            throw new \RuntimeException($this->getPath().': An ARRAY node can have a specified default value only when using a prototype');
        }

        $defaults = array();
        foreach ($this->children as $name => $child) {
            if (!$child->hasDefaultValue()) {
                continue;
            }

            $defaults[$name] = $child->getDefaultValue();
        }

        return $defaults;
    }

    public function setPrototype(PrototypeNodeInterface $node)
    {
        if (count($this->children) > 0) {
            throw new \RuntimeException($this->getPath().': An ARRAY node must either have concrete children, or a prototype node.');
        }

        $this->prototype = $node;
    }

    public function addChild(NodeInterface $node)
    {
        $name = $node->getName();
        if (empty($name)) {
            throw new \InvalidArgumentException('Node name cannot be empty.');
        }
        if (isset($this->children[$name])) {
            throw new \InvalidArgumentException(sprintf('The node "%s" already exists.', $name));
        }
        if (null !== $this->prototype) {
            throw new \RuntimeException('An ARRAY node must either have a prototype, or concrete children.');
        }

        $this->children[$name] = $node;
    }

    protected function finalizeValue($value)
    {
        if (false === $value) {
            throw new UnsetKeyException(sprintf(
                'Unsetting key for path "%s", value: %s',
                $this->getPath(),
                json_encode($value)
            ));
        }

        if (null !== $this->prototype) {
            foreach ($value as $k => $v) {
                try {
                    $value[$k] = $this->prototype->finalize($v);
                } catch (UnsetKeyException $unset) {
                    unset($value[$k]);
                }
            }

            if (count($value) < $this->minNumberOfElements) {
                throw new InvalidConfigurationException(sprintf(
                    'You must define at least %d element(s) for path "%s".',
                    $this->minNumberOfElements,
                    $this->getPath()
                ));
            }

            return $value;
        }

        foreach ($this->children as $name => $child) {
            if (!array_key_exists($name, $value)) {
                if ($child->isRequired()) {
                    throw new InvalidConfigurationException(sprintf(
                        'The node at path "%s" must be configured.',
                        $this->getPath()
                    ));
                }

                if ($child->hasDefaultValue())  {
                    $value[$name] = $child->getDefaultValue();
                }

                continue;
            }

            try {
                $value[$name] = $child->finalize($value[$name]);
            } catch (UnsetKeyException $unset) {
                unset($value[$name]);
            }
        }

        return $value;
    }

    protected function validateType($value)
    {
        if (!is_array($value) && (!$this->allowFalse || false !== $value)) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected array, but got %s',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }

    protected function normalizeValue($value)
    {
        if (false === $value) {
            return $value;
        }

        foreach ($this->xmlRemappings as $transformation) {
            list($singular, $plural) = $transformation;

            if (!isset($value[$singular])) {
                continue;
            }

            $value[$plural] = Extension::normalizeConfig($value, $singular, $plural);
        }

        if (null !== $this->prototype) {
            $normalized = array();
            foreach ($value as $k => $v) {
                if (null !== $this->keyAttribute && is_array($v)) {
                    if (!isset($v[$this->keyAttribute]) && is_int($k)) {
                        throw new InvalidConfigurationException(sprintf(
                            'You must set a "%s" attribute for path "%s".',
                            $this->keyAttribute,
                            $this->getPath()
                        ));
                    } else if (isset($v[$this->keyAttribute])) {
                        $k = $v[$this->keyAttribute];
                    }

                    if (array_key_exists($k, $normalized)) {
                        throw new DuplicateKeyException(sprintf(
                            'Duplicate key "%s" for path "%s".',
                            $k,
                            $this->getPath()
                        ));
                    }
                }

                $this->prototype->setName($k);
                if (null !== $this->keyAttribute) {
                    $normalized[$k] = $this->prototype->normalize($v);
                } else {
                    $normalized[] = $this->prototype->normalize($v);
                }
            }

            return $normalized;
        }

        $normalized = array();
        foreach ($this->children as $name => $child) {
            if (!array_key_exists($name, $value)) {
                continue;
            }

            $normalized[$name] = $child->normalize($value[$name]);
        }

        return $normalized;
    }

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
            if (null !== $this->prototype && null === $this->keyAttribute) {
                $leftSide[] = $v;
                continue;
            }

            // no conflict
            if (!array_key_exists($k, $leftSide)) {
                if (!$this->allowNewKeys) {
                    throw new InvalidConfigurationException(sprintf(
                        'You are not allowed to define new elements for path "%s". '
                       .'Please define all elements for this path in one config file.',
                        $this->getPath()
                    ));
                }

                $leftSide[$k] = $v;
                continue;
            }

            try {
                if (null !== $this->prototype) {
                    $this->prototype->setName($k);
                    $leftSide[$k] = $this->prototype->merge($leftSide[$k], $v);
                } else {
                    if (!isset($this->children[$k])) {
                        throw new \RuntimeException('merge() expects a normalized config array.');
                    }

                    $leftSide[$k] = $this->children[$k]->merge($leftSide[$k], $v);
                }
            } catch (UnsetKeyException $unset) {
                unset($leftSide[$k]);
            }
        }

        return $leftSide;
    }
}