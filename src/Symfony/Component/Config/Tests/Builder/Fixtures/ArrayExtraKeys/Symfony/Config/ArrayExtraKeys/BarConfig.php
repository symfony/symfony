<?php

namespace Symfony\Config\ArrayExtraKeys;


use Symfony\Component\Config\Loader\ParamConfigurator;


/**
 * This class is automatically generated to help creating config.
 */
class BarConfig 
{
    private $corge;
    private $grault;
    private $_extraKeys;
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function corge($value): static
    {
        $this->corge = $value;
    
        return $this;
    }
    
    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function grault($value): static
    {
        $this->grault = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['corge'])) {
            $this->corge = $value['corge'];
            unset($value['corge']);
        }
    
        if (isset($value['grault'])) {
            $this->grault = $value['grault'];
            unset($value['grault']);
        }
    
        $this->_extraKeys = $value;
    
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->corge) {
            $output['corge'] = $this->corge;
        }
        if (null !== $this->grault) {
            $output['grault'] = $this->grault;
        }
    
        return $output + $this->_extraKeys;
    }
    
    /**
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function set(string $key, mixed $value): static
    {
        if (null === $value) {
            unset($this->_extraKeys[$key]);
        } else {
            $this->_extraKeys[$key] = $value;
        }
    
        return $this;
    }

}
