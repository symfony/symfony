<?php

namespace Symfony\Config\AddToList;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class TranslatorConfig 
{
    private $fallbacks;
    private $sources;
    
    /**
     * @param ParamConfigurator|list<mixed|ParamConfigurator> $value
     * @return $this
     */
    public function fallbacks($value): self
    {
        $this->fallbacks = $value;
    
        return $this;
    }
    
    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function source(string $source_class, $value): self
    {
        $this->sources[$source_class] = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['fallbacks'])) {
            $this->fallbacks = $value['fallbacks'];
            unset($value['fallbacks']);
        }
    
        if (isset($value['sources'])) {
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
        if (null !== $this->fallbacks) {
            $output['fallbacks'] = $this->fallbacks;
        }
        if (null !== $this->sources) {
            $output['sources'] = $this->sources;
        }
    
        return $output;
    }

}
