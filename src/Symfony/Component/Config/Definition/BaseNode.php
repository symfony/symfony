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

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Config\Definition\Exception\ForbiddenOverwriteException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * The base node class.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class BaseNode implements NodeInterface
{
    protected $name;
    protected $parent;
    protected $normalizationClosures = [];
    protected $finalValidationClosures = [];
    protected $allowOverwrite = true;
    protected $required = false;
    protected $deprecationMessage = null;
    protected $equivalentValues = [];
    protected $attributes = [];

    /**
     * @param string|null        $name   The name of the node
     * @param NodeInterface|null $parent The parent of this node
     *
     * @throws \InvalidArgumentException if the name contains a period
     */
    public function __construct($name, NodeInterface $parent = null)
    {
        if (false !== strpos($name = (string) $name, '.')) {
            throw new \InvalidArgumentException('The name must not contain ".".');
        }

        $this->name = $name;
        $this->parent = $parent;
    }

    /**
     * @param string $key
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : $default;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @param string $key
     */
    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Sets an info message.
     *
     * @param string $info
     */
    public function setInfo($info)
    {
        $this->setAttribute('info', $info);
    }

    /**
     * Returns info message.
     *
     * @return string|null The info text
     */
    public function getInfo()
    {
        return $this->getAttribute('info');
    }

    /**
     * Sets the example configuration for this node.
     *
     * @param string|array $example
     */
    public function setExample($example)
    {
        $this->setAttribute('example', $example);
    }

    /**
     * Retrieves the example configuration for this node.
     *
     * @return string|array|null The example
     */
    public function getExample()
    {
        return $this->getAttribute('example');
    }

    /**
     * Adds an equivalent value.
     *
     * @param mixed $originalValue
     * @param mixed $equivalentValue
     */
    public function addEquivalentValue($originalValue, $equivalentValue)
    {
        $this->equivalentValues[] = [$originalValue, $equivalentValue];
    }

    /**
     * Set this node as required.
     *
     * @param bool $boolean Required node
     */
    public function setRequired($boolean)
    {
        $this->required = (bool) $boolean;
    }

    /**
     * Sets this node as deprecated.
     *
     * You can use %node% and %path% placeholders in your message to display,
     * respectively, the node name and its complete path.
     *
     * @param string|null $message Deprecated message
     */
    public function setDeprecated($message)
    {
        $this->deprecationMessage = $message;
    }

    /**
     * Sets if this node can be overridden.
     *
     * @param bool $allow
     */
    public function setAllowOverwrite($allow)
    {
        $this->allowOverwrite = (bool) $allow;
    }

    /**
     * Sets the closures used for normalization.
     *
     * @param \Closure[] $closures An array of Closures used for normalization
     */
    public function setNormalizationClosures(array $closures)
    {
        $this->normalizationClosures = $closures;
    }

    /**
     * Sets the closures used for final validation.
     *
     * @param \Closure[] $closures An array of Closures used for final validation
     */
    public function setFinalValidationClosures(array $closures)
    {
        $this->finalValidationClosures = $closures;
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Checks if this node is deprecated.
     *
     * @return bool
     */
    public function isDeprecated()
    {
        return null !== $this->deprecationMessage;
    }

    /**
     * Returns the deprecated message.
     *
     * @param string $node the configuration node name
     * @param string $path the path of the node
     *
     * @return string
     */
    public function getDeprecationMessage($node, $path)
    {
        return strtr($this->deprecationMessage, ['%node%' => $node, '%path%' => $path]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        $path = $this->name;

        if (null !== $this->parent) {
            $path = $this->parent->getPath().'.'.$path;
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    final public function merge($leftSide, $rightSide)
    {
        if (!$this->allowOverwrite) {
            throw new ForbiddenOverwriteException(sprintf('Configuration path "%s" cannot be overwritten. You have to define all options for this path, and any of its sub-paths in one configuration section.', $this->getPath()));
        }

        $this->validateType($leftSide);
        $this->validateType($rightSide);

        return $this->mergeValues($leftSide, $rightSide);
    }

    /**
     * {@inheritdoc}
     */
    final public function normalize($value)
    {
        $value = $this->preNormalize($value);

        // run custom normalization closures
        foreach ($this->normalizationClosures as $closure) {
            $value = $closure($value);
        }

        // replace value with their equivalent
        foreach ($this->equivalentValues as $data) {
            if ($data[0] === $value) {
                $value = $data[1];
            }
        }

        // validate type
        $this->validateType($value);

        // normalize value
        return $this->normalizeValue($value);
    }

    /**
     * Normalizes the value before any other normalization is applied.
     *
     * @param mixed $value
     *
     * @return mixed The normalized array value
     */
    protected function preNormalize($value)
    {
        return $value;
    }

    /**
     * Returns parent node for this node.
     *
     * @return NodeInterface|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    final public function finalize($value)
    {
        $this->validateType($value);

        $value = $this->finalizeValue($value);

        // Perform validation on the final value if a closure has been set.
        // The closure is also allowed to return another value.
        foreach ($this->finalValidationClosures as $closure) {
            try {
                $value = $closure($value);
            } catch (Exception $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new InvalidConfigurationException(sprintf('Invalid configuration for path "%s": %s', $this->getPath(), $e->getMessage()), $e->getCode(), $e);
            }
        }

        return $value;
    }

    /**
     * Validates the type of a Node.
     *
     * @param mixed $value The value to validate
     *
     * @throws InvalidTypeException when the value is invalid
     */
    abstract protected function validateType($value);

    /**
     * Normalizes the value.
     *
     * @param mixed $value The value to normalize
     *
     * @return mixed The normalized value
     */
    abstract protected function normalizeValue($value);

    /**
     * Merges two values together.
     *
     * @param mixed $leftSide
     * @param mixed $rightSide
     *
     * @return mixed The merged value
     */
    abstract protected function mergeValues($leftSide, $rightSide);

    /**
     * Finalizes a value.
     *
     * @param mixed $value The value to finalize
     *
     * @return mixed The finalized value
     */
    abstract protected function finalizeValue($value);
}
