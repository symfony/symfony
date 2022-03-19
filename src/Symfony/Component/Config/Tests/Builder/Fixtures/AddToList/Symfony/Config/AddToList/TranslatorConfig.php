<?php

namespace Symfony\Config\AddToList;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help in creating a config.
 */
class TranslatorConfig 
{
    private $fallbacks;
    private $sources;
    private $_usedProperties = [];
    
    /**
     * @param ParamConfigurator|list<mixed|ParamConfigurator> $value
     * @return $this
     */
    public function fallbacks($value): self
    {
        $this->_usedProperties['fallbacks'] = true;
        $this->fallbacks = $value;
    
        return $this;
    }
    
    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function source(string $source_class, $value): self
    {
        $this->_usedProperties['sources'] = true;
        $this->sources[$source_class] = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (array_key_exists('fallbacks', $value)) {
            $this->_usedProperties['fallbacks'] = true;
            $this->fallbacks = $value['fallbacks'];
            unset($value['fallbacks']);
        }
    
        if (array_key_exists('sources', $value)) {
            $this->_usedProperties['sources'] = true;
            $this->sources = $value['sources'];
            unset($value['sources']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['fallbacks'])) {
            $output['fallbacks'] = $this->fallbacks;
        }
        if (isset($this->_usedProperties['sources'])) {
            $output['sources'] = $this->sources;
        }
    
        return $output;
    }

}
