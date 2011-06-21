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

use Symfony\Component\Config\Definition\Exception\ForbiddenOverwriteException;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\DuplicateKeyException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Exception\UnsetKeyException;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * Represents an Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ArrayNode extends BaseNode implements PrototypeNodeInterface
{
    protected $xmlRemappings;
    protected $children;
    protected $allowFalse;
    protected $allowNewKeys;
    protected $addIfNotSet;
    protected $performDeepMerging;
    protected $ignoreExtraKeys;

    /**
     * Constructor.
     *
     * @param string $name The Node's name
     * @param NodeInterface $parent The node parent
     */
    public function __construct($name, NodeInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->children = array();
        $this->xmlRemappings = array();
        $this->removeKeyAttribute = true;
        $this->allowFalse = false;
        $this->addIfNotSet = false;
        $this->allowNewKeys = true;
        $this->performDeepMerging = true;
    }

    /**
     * Sets the xml remappings that should be performed.
     *
     * @param array $remappings an array of the form array(array(string, string))
     */
    public function setXmlRemappings(array $remappings)
    {
        $this->xmlRemappings = $remappings;
    }

    /**
     * Sets whether to add default values for this array if it has not been
     * defined in any of the configuration files.
     *
     * @param Boolean $boolean
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
     */
    public function setAllowFalse($allow)
    {
        $this->allowFalse = (Boolean) $allow;
    }

    /**
     * Sets whether new keys can be defined in subsequent configurations.
     *
     * @param Boolean $allow
     */
    public function setAllowNewKeys($allow)
    {
        $this->allowNewKeys = (Boolean) $allow;
    }

    /**
     * Sets if deep merging should occur.
     *
     * @param Boolean $boolean
     */
    public function setPerformDeepMerging($boolean)
    {
        $this->performDeepMerging = (Boolean) $boolean;
    }

    /**
     * Whether extra keys should just be ignore without an exception.
     *
     * @param Boolean $boolean To allow extra keys
     */
    public function setIgnoreExtraKeys($boolean)
    {
        $this->ignoreExtraKeys = (Boolean) $boolean;
    }

    /**
     * Sets the node Name.
     *
     * @param string $name The node's name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Checks if the node has a default value.
     *
     * @return Boolean
     */
    public function hasDefaultValue()
    {
        return $this->addIfNotSet;
    }

    /**
     * Retrieves the default value.
     *
     * @return array The default value
     * @throws \RuntimeException if the node has no default value
     */
    public function getDefaultValue()
    {
        if (!$this->hasDefaultValue()) {
            throw new \RuntimeException(sprintf('The node at path "%s" has no default value.', $this->getPath()));
        }

        $defaults = array();
        foreach ($this->children as $name => $child) {
            if ($child->hasDefaultValue()) {
                $defaults[$name] = $child->getDefaultValue();
            }
        }

        return $defaults;
    }

    /**
     * Adds a child node.
     *
     * @param NodeInterface $node The child node to add
     * @throws \InvalidArgumentException when the child node has no name
     * @throws \InvalidArgumentException when the child node's name is not unique
     */
    public function addChild(NodeInterface $node)
    {
        $name = $node->getName();
        if (empty($name)) {
            throw new \InvalidArgumentException('Child nodes must be named.');
        }
        if (isset($this->children[$name])) {
            throw new \InvalidArgumentException(sprintf('A child node named "%s" already exists.', $name));
        }

        $this->children[$name] = $node;
    }

    /**
     * Finalizes the value of this node.
     *
     * @param mixed $value
     * @return mixed The finalised value
     * @throws UnsetKeyException
     * @throws InvalidConfigurationException if the node doesn't have enough children
     */
    protected function finalizeValue($value)
    {
        if (false === $value) {
            $msg = sprintf('Unsetting key for path "%s", value: %s', $this->getPath(), json_encode($value));
            throw new UnsetKeyException($msg);
        }

        foreach ($this->children as $name => $child) {
            if (!array_key_exists($name, $value)) {
                if ($child->isRequired()) {
                    $msg = sprintf('The child node "%s" at path "%s" must be configured.', $name, $this->getPath());
                    $ex = new InvalidConfigurationException($msg);
                    $ex->setPath($this->getPath());

                    throw $ex;
                }

                if ($child->hasDefaultValue()) {
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

    /**
     * Validates the type of the value.
     *
     * @param mixed $value
     * @throws InvalidTypeException
     */
    protected function validateType($value)
    {
        if (!is_array($value) && (!$this->allowFalse || false !== $value)) {
            $ex = new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected array, but got %s',
                $this->getPath(),
                gettype($value)
            ));
            $ex->setPath($this->getPath());

            throw $ex;
        }
    }

    /**
     * Normalizes the value.
     *
     * @param mixed $value The value to normalize
     * @return mixed The normalized value
     */
    protected function normalizeValue($value)
    {
        if (false === $value) {
            return $value;
        }

        $value = $this->remapXml($value);

        $normalized = array();
        foreach ($this->children as $name => $child) {
            if (array_key_exists($name, $value)) {
                $normalized[$name] = $child->normalize($value[$name]);
                unset($value[$name]);
            }
        }

        // if extra fields are present, throw exception
        if (count($value) && !$this->ignoreExtraKeys) {
            $msg = sprintf('Unrecognized options "%s" under "%s"', implode(', ', array_keys($value)), $this->getPath());
            $ex = new InvalidConfigurationException($msg);
            $ex->setPath($this->getPath().'.'.reset($value));

            throw $ex;
        }

        return $normalized;
    }

    /**
     * Remap multiple singular values to a single plural value
     *
     * @param array $value The source values
     * @return array The remapped values
     */
    protected function remapXml($value)
    {
        foreach ($this->xmlRemappings as $transformation) {
            list($singular, $plural) = $transformation;

            if (!isset($value[$singular])) {
                continue;
            }

            $value[$plural] = Processor::normalizeConfig($value, $singular, $plural);
            unset($value[$singular]);
        }

        return $value;
    }

    /**
     * Merges values together.
     *
     * @param mixed $leftSide The left side to merge.
     * @param mixed $rightSide The right side to merge.
     * @return mixed The merged values
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
            // no conflict
            if (!array_key_exists($k, $leftSide)) {
                if (!$this->allowNewKeys) {
                    $ex = new InvalidConfigurationException(sprintf(
                        'You are not allowed to define new elements for path "%s". '
                       .'Please define all elements for this path in one config file. '
                       .'If you are trying to overwrite an element, make sure you redefine it '
                       .'with the same name.',
                        $this->getPath()
                    ));
                    $ex->setPath($this->getPath());

                    throw $ex;
                }

                $leftSide[$k] = $v;
                continue;
            }

            if (!isset($this->children[$k])) {
                throw new \RuntimeException('merge() expects a normalized config array.');
            }

            $leftSide[$k] = $this->children[$k]->merge($leftSide[$k], $v);
        }

        return $leftSide;
    }
}
