<?php

namespace Symfony\Config\NodeInitialValues;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class SomeCleverNameConfig 
{
    private $first;
    private $second;
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function first($value): static
    {
        $this->first = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function second($value): static
    {
        $this->second = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['first'])) {
            $this->first = $value['first'];
            unset($value['first']);
        }
    
        if (isset($value['second'])) {
            $this->second = $value['second'];
            unset($value['second']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->first) {
            $output['first'] = $this->first;
        }
        if (null !== $this->second) {
            $output['second'] = $this->second;
        }
    
        return $output;
    }

}
