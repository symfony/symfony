<?php

namespace Symfony\Config;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class VariableTypeConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $anyValue;
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function anyValue(mixed $value): static
    {
        $this->anyValue = $value;
    
        return $this;
    }
    
    public function getExtensionAlias(): string
    {
        return 'variable_type';
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['any_value'])) {
            $this->anyValue = $value['any_value'];
            unset($value['any_value']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->anyValue) {
            $output['any_value'] = $this->anyValue;
        }
    
        return $output;
    }

}
