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
    private $fqcnEnumNode;
    private $fqcnUnitEnumNode;
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
     * @param ParamConfigurator|\Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum::Foo|\Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum::Bar $value
     * @return $this
     */
    public function fqcnEnumNode($value): static
    {
        $this->_usedProperties['fqcnEnumNode'] = true;
        $this->fqcnEnumNode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|\Symfony\Component\Config\Tests\Fixtures\TestEnum::Foo|\Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar|\Symfony\Component\Config\Tests\Fixtures\TestEnum::Ccc $value
     * @return $this
     */
    public function fqcnUnitEnumNode($value): static
    {
        $this->_usedProperties['fqcnUnitEnumNode'] = true;
        $this->fqcnUnitEnumNode = $value;

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

    /**
     * @param array{
     *     boolean_node?: bool,
     *     enum_node?: "foo"|"bar"|"baz"|Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar,
     *     fqcn_enum_node?: foo|bar,
     *     fqcn_unit_enum_node?: Symfony\Component\Config\Tests\Fixtures\TestEnum::Foo|Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar|Symfony\Component\Config\Tests\Fixtures\TestEnum::Ccc,
     *     float_node?: float,
     *     integer_node?: int<min, max>,
     *     scalar_node?: scalar|null,
     *     scalar_node_with_default?: scalar|null, // Default: true
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('boolean_node', $config)) {
            $this->_usedProperties['booleanNode'] = true;
            $this->booleanNode = $config['boolean_node'];
            unset($config['boolean_node']);
        }

        if (array_key_exists('enum_node', $config)) {
            $this->_usedProperties['enumNode'] = true;
            $this->enumNode = $config['enum_node'];
            unset($config['enum_node']);
        }

        if (array_key_exists('fqcn_enum_node', $config)) {
            $this->_usedProperties['fqcnEnumNode'] = true;
            $this->fqcnEnumNode = $config['fqcn_enum_node'];
            unset($config['fqcn_enum_node']);
        }

        if (array_key_exists('fqcn_unit_enum_node', $config)) {
            $this->_usedProperties['fqcnUnitEnumNode'] = true;
            $this->fqcnUnitEnumNode = $config['fqcn_unit_enum_node'];
            unset($config['fqcn_unit_enum_node']);
        }

        if (array_key_exists('float_node', $config)) {
            $this->_usedProperties['floatNode'] = true;
            $this->floatNode = $config['float_node'];
            unset($config['float_node']);
        }

        if (array_key_exists('integer_node', $config)) {
            $this->_usedProperties['integerNode'] = true;
            $this->integerNode = $config['integer_node'];
            unset($config['integer_node']);
        }

        if (array_key_exists('scalar_node', $config)) {
            $this->_usedProperties['scalarNode'] = true;
            $this->scalarNode = $config['scalar_node'];
            unset($config['scalar_node']);
        }

        if (array_key_exists('scalar_node_with_default', $config)) {
            $this->_usedProperties['scalarNodeWithDefault'] = true;
            $this->scalarNodeWithDefault = $config['scalar_node_with_default'];
            unset($config['scalar_node_with_default']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
        if (isset($this->_usedProperties['fqcnEnumNode'])) {
            $output['fqcn_enum_node'] = $this->fqcnEnumNode;
        }
        if (isset($this->_usedProperties['fqcnUnitEnumNode'])) {
            $output['fqcn_unit_enum_node'] = $this->fqcnUnitEnumNode;
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
