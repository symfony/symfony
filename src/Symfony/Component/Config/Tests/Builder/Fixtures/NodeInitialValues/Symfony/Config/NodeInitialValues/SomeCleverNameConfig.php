<?php

namespace Symfony\Config\NodeInitialValues;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SomeCleverNameConfig 
{
    private $first;
    private $second;
    private $third;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function first($value): static
    {
        $this->_usedProperties['first'] = true;
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
        $this->_usedProperties['second'] = true;
        $this->second = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function third($value): static
    {
        $this->_usedProperties['third'] = true;
        $this->third = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('first', $value)) {
            $this->_usedProperties['first'] = true;
            $this->first = $value['first'];
            unset($value['first']);
        }

        if (array_key_exists('second', $value)) {
            $this->_usedProperties['second'] = true;
            $this->second = $value['second'];
            unset($value['second']);
        }

        if (array_key_exists('third', $value)) {
            $this->_usedProperties['third'] = true;
            $this->third = $value['third'];
            unset($value['third']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['first'])) {
            $output['first'] = $this->first;
        }
        if (isset($this->_usedProperties['second'])) {
            $output['second'] = $this->second;
        }
        if (isset($this->_usedProperties['third'])) {
            $output['third'] = $this->third;
        }

        return $output;
    }

}
