<?php

namespace Symfony\Config\ArrayExtraKeys;


use Symfony\Component\Config\Loader\ParamConfigurator;


/**
 * This class is automatically generated to help creating config.
 */
class FooConfig 
{
    private $baz;
    private $qux;
    private $_extraKeys;
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function baz($value): self
    {
        $this->baz = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function qux($value): self
    {
        $this->qux = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['baz'])) {
            $this->baz = $value['baz'];
            unset($value['baz']);
        }
    
        if (isset($value['qux'])) {
            $this->qux = $value['qux'];
            unset($value['qux']);
        }
    
        $this->_extraKeys = $value;
    
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->baz) {
            $output['baz'] = $this->baz;
        }
        if (null !== $this->qux) {
            $output['qux'] = $this->qux;
        }
    
        return $output + $this->_extraKeys;
    }
    
    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function set(string $key, $value): self
    {
        if (null === $value) {
            unset($this->_extraKeys[$key]);
        } else {
            $this->_extraKeys[$key] = $value;
        }
    
        return $this;
    }

}
