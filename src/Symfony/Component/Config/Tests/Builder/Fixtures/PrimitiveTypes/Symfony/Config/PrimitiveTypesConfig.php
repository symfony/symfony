<?php

namespace Symfony\Config;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class PrimitiveTypesConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $booleanNode;
    private $enumNode;
    private $floatNode;
    private $integerNode;
    private $scalarNode;
    
    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function booleanNode($value): self
    {
        $this->booleanNode = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|'foo'|'bar'|'baz' $value
     * @return $this
     */
    public function enumNode($value): self
    {
        $this->enumNode = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function floatNode($value): self
    {
        $this->floatNode = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function integerNode($value): self
    {
        $this->integerNode = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function scalarNode($value): self
    {
        $this->scalarNode = $value;
    
        return $this;
    }
    
    public function getExtensionAlias(): string
    {
        return 'primitive_types';
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['boolean_node'])) {
            $this->booleanNode = $value['boolean_node'];
            unset($value['boolean_node']);
        }
    
        if (isset($value['enum_node'])) {
            $this->enumNode = $value['enum_node'];
            unset($value['enum_node']);
        }
    
        if (isset($value['float_node'])) {
            $this->floatNode = $value['float_node'];
            unset($value['float_node']);
        }
    
        if (isset($value['integer_node'])) {
            $this->integerNode = $value['integer_node'];
            unset($value['integer_node']);
        }
    
        if (isset($value['scalar_node'])) {
            $this->scalarNode = $value['scalar_node'];
            unset($value['scalar_node']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->booleanNode) {
            $output['boolean_node'] = $this->booleanNode;
        }
        if (null !== $this->enumNode) {
            $output['enum_node'] = $this->enumNode;
        }
        if (null !== $this->floatNode) {
            $output['float_node'] = $this->floatNode;
        }
        if (null !== $this->integerNode) {
            $output['integer_node'] = $this->integerNode;
        }
        if (null !== $this->scalarNode) {
            $output['scalar_node'] = $this->scalarNode;
        }
    
        return $output;
    }

}
