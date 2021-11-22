<?php

namespace Symfony\Config\NodeInitialValues\Messenger;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class TransportsConfig 
{
    private $dsn;
    private $serializer;
    private $options;
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dsn($value): self
    {
        $this->dsn = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function serializer($value): self
    {
        $this->serializer = $value;
    
        return $this;
    }
    
    /**
     * @param ParamConfigurator|list<mixed|ParamConfigurator> $value
     * @return $this
     */
    public function options($value): self
    {
        $this->options = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['dsn'])) {
            $this->dsn = $value['dsn'];
            unset($value['dsn']);
        }
    
        if (isset($value['serializer'])) {
            $this->serializer = $value['serializer'];
            unset($value['serializer']);
        }
    
        if (isset($value['options'])) {
            $this->options = $value['options'];
            unset($value['options']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->dsn) {
            $output['dsn'] = $this->dsn;
        }
        if (null !== $this->serializer) {
            $output['serializer'] = $this->serializer;
        }
        if (null !== $this->options) {
            $output['options'] = $this->options;
        }
    
        return $output;
    }

}
