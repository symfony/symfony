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

use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;
use Symfony\Component\Config\Definition\NodeInterface;

/**
 * This class provides a fluent interface for defining a node.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class NodeDefinition implements NodeParentInterface
{
    protected ?string $name = null;
    protected NormalizationBuilder $normalization;
    protected ValidationBuilder $validation;
    protected mixed $defaultValue;
    protected bool $default = false;
    protected bool $required = false;
    protected array $deprecation = [];
    protected MergeBuilder $merge;
    protected bool $allowEmptyValue = true;
    protected mixed $nullEquivalent = null;
    protected mixed $trueEquivalent = true;
    protected mixed $falseEquivalent = false;
    protected string $pathSeparator = BaseNode::DEFAULT_PATH_SEPARATOR;
    protected NodeParentInterface|NodeInterface|null $parent;
    protected array $attributes = [];

    public function __construct(?string $name, NodeParentInterface $parent = null)
    {
        $this->parent = $parent;
        $this->name = $name;
    }

    /**
     * Sets the parent node.
     *
     * @return $this
     */
    public function setParent(NodeParentInterface $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Sets info message.
     *
     * @return $this
     */
    public function info(string $info): static
    {
        return $this->attribute('info', $info);
    }

    /**
     * Sets example configuration.
     *
     * @return $this
     */
    public function example(string|array $example): static
    {
        return $this->attribute('example', $example);
    }

    /**
     * Sets an attribute on the node.
     *
     * @return $this
     */
    public function attribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Returns the parent node.
     *
     * @return NodeParentInterface|NodeBuilder|self|ArrayNodeDefinition|VariableNodeDefinition
     */
    public function end(): NodeParentInterface
    {
        return $this->parent;
    }

    /**
     * Creates the node.
     */
    public function getNode(bool $forceRootNode = false): NodeInterface
    {
        if ($forceRootNode) {
            $this->parent = null;
        }

        if (isset($this->normalization)) {
            $allowedTypes = [];
            foreach ($this->normalization->before as $expr) {
                $allowedTypes[] = $expr->allowedTypes;
            }
            $allowedTypes = array_unique($allowedTypes);
            $this->normalization->before = ExprBuilder::buildExpressions($this->normalization->before);
            $this->normalization->declaredTypes = $allowedTypes;
        }

        if (isset($this->validation)) {
            $this->validation->rules = ExprBuilder::buildExpressions($this->validation->rules);
        }

        $node = $this->createNode();
        if ($node instanceof BaseNode) {
            $node->setAttributes($this->attributes);
        }

        return $node;
    }

    /**
     * Sets the default value.
     *
     * @return $this
     */
    public function defaultValue(mixed $value): static
    {
        $this->default = true;
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Sets the node as required.
     *
     * @return $this
     */
    public function isRequired(): static
    {
        $this->required = true;

        return $this;
    }

    /**
     * Sets the node as deprecated.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message the deprecation message to use
     *
     * You can use %node% and %path% placeholders in your message to display,
     * respectively, the node name and its complete path
     *
     * @return $this
     */
    public function setDeprecated(string $package, string $version, string $message = 'The child node "%node%" at path "%path%" is deprecated.'): static
    {
        $this->deprecation = [
            'package' => $package,
            'version' => $version,
            'message' => $message,
        ];

        return $this;
    }

    /**
     * Sets the equivalent value used when the node contains null.
     *
     * @return $this
     */
    public function treatNullLike(mixed $value): static
    {
        $this->nullEquivalent = $value;

        return $this;
    }

    /**
     * Sets the equivalent value used when the node contains true.
     *
     * @return $this
     */
    public function treatTrueLike(mixed $value): static
    {
        $this->trueEquivalent = $value;

        return $this;
    }

    /**
     * Sets the equivalent value used when the node contains false.
     *
     * @return $this
     */
    public function treatFalseLike(mixed $value): static
    {
        $this->falseEquivalent = $value;

        return $this;
    }

    /**
     * Sets null as the default value.
     *
     * @return $this
     */
    public function defaultNull(): static
    {
        return $this->defaultValue(null);
    }

    /**
     * Sets true as the default value.
     *
     * @return $this
     */
    public function defaultTrue(): static
    {
        return $this->defaultValue(true);
    }

    /**
     * Sets false as the default value.
     *
     * @return $this
     */
    public function defaultFalse(): static
    {
        return $this->defaultValue(false);
    }

    /**
     * Sets an expression to run before the normalization.
     */
    public function beforeNormalization(): ExprBuilder
    {
        return $this->normalization()->before();
    }

    /**
     * Denies the node value being empty.
     *
     * @return $this
     */
    public function cannotBeEmpty(): static
    {
        $this->allowEmptyValue = false;

        return $this;
    }

    /**
     * Sets an expression to run for the validation.
     *
     * The expression receives the value of the node and must return it. It can
     * modify it.
     * An exception should be thrown when the node is not valid.
     */
    public function validate(): ExprBuilder
    {
        return $this->validation()->rule();
    }

    /**
     * Sets whether the node can be overwritten.
     *
     * @return $this
     */
    public function cannotBeOverwritten(bool $deny = true): static
    {
        $this->merge()->denyOverwrite($deny);

        return $this;
    }

    /**
     * Gets the builder for validation rules.
     */
    protected function validation(): ValidationBuilder
    {
        return $this->validation ??= new ValidationBuilder($this);
    }

    /**
     * Gets the builder for merging rules.
     */
    protected function merge(): MergeBuilder
    {
        return $this->merge ??= new MergeBuilder($this);
    }

    /**
     * Gets the builder for normalization rules.
     */
    protected function normalization(): NormalizationBuilder
    {
        return $this->normalization ??= new NormalizationBuilder($this);
    }

    /**
     * Instantiate and configure the node according to this definition.
     *
     * @throws InvalidDefinitionException When the definition is invalid
     */
    abstract protected function createNode(): NodeInterface;

    /**
     * Set PathSeparator to use.
     *
     * @return $this
     */
    public function setPathSeparator(string $separator): static
    {
        if ($this instanceof ParentNodeDefinitionInterface) {
            foreach ($this->getChildNodeDefinitions() as $child) {
                $child->setPathSeparator($separator);
            }
        }

        $this->pathSeparator = $separator;

        return $this;
    }
}
