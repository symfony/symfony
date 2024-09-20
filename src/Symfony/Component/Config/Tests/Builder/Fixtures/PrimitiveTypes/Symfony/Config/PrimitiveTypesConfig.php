<?php

namespace Symfony\Config;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PrimitiveTypesConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $booleanNode;
    private $enumNode;
    private $floatNode;
    private $integerNode;
    private $scalarNode;
    private $scalarNodeWithDefault;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function booleanNode($value): static
    {
        $this->_usedProperties['booleanNode'] = true;
        $this->booleanNode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|'foo'|'bar'|'baz'|\Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar $value
     * @return $this
     */
    public function enumNode($value): static
    {
        $this->_usedProperties['enumNode'] = true;
        $this->enumNode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function floatNode($value): static
    {
        $this->_usedProperties['floatNode'] = true;
        $this->floatNode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function integerNode($value): static
    {
        $this->_usedProperties['integerNode'] = true;
        $this->integerNode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function scalarNode($value): static
    {
        $this->_usedProperties['scalarNode'] = true;
        $this->scalarNode = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function scalarNodeWithDefault($value): static
    {
        $this->_usedProperties['scalarNodeWithDefault'] = true;
        $this->scalarNodeWithDefault = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'primitive_types';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('boolean_node', $value)) {
            $this->_usedProperties['booleanNode'] = true;
            $this->booleanNode = $value['boolean_node'];
            unset($value['boolean_node']);
        }

        if (array_key_exists('enum_node', $value)) {
            $this->_usedProperties['enumNode'] = true;
            $this->enumNode = $value['enum_node'];
            unset($value['enum_node']);
        }

        if (array_key_exists('float_node', $value)) {
            $this->_usedProperties['floatNode'] = true;
            $this->floatNode = $value['float_node'];
            unset($value['float_node']);
        }

        if (array_key_exists('integer_node', $value)) {
            $this->_usedProperties['integerNode'] = true;
            $this->integerNode = $value['integer_node'];
            unset($value['integer_node']);
        }

        if (array_key_exists('scalar_node', $value)) {
            $this->_usedProperties['scalarNode'] = true;
            $this->scalarNode = $value['scalar_node'];
            unset($value['scalar_node']);
        }

        if (array_key_exists('scalar_node_with_default', $value)) {
            $this->_usedProperties['scalarNodeWithDefault'] = true;
            $this->scalarNodeWithDefault = $value['scalar_node_with_default'];
            unset($value['scalar_node_with_default']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['booleanNode'])) {
            $output['boolean_node'] = $this->booleanNode;
        }
        if (isset($this->_usedProperties['enumNode'])) {
            $output['enum_node'] = $this->enumNode;
        }
        if (isset($this->_usedProperties['floatNode'])) {
            $output['float_node'] = $this->floatNode;
        }
        if (isset($this->_usedProperties['integerNode'])) {
            $output['integer_node'] = $this->integerNode;
        }
        if (isset($this->_usedProperties['scalarNode'])) {
            $output['scalar_node'] = $this->scalarNode;
        }
        if (isset($this->_usedProperties['scalarNodeWithDefault'])) {
            $output['scalar_node_with_default'] = $this->scalarNodeWithDefault;
        }

        return $output;
    }

}
