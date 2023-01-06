<?php

namespace Symfony\Config\ArrayExtraKeys;

use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class BarConfig 
{
    private $corge;
    private $grault;
    private $_usedProperties = [];
    private $_extraKeys;

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function corge($value): static
    {
        $this->_usedProperties['corge'] = true;
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
        $this->_usedProperties['grault'] = true;
        $this->grault = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('corge', $value)) {
            $this->_usedProperties['corge'] = true;
            $this->corge = $value['corge'];
            unset($value['corge']);
        }

        if (array_key_exists('grault', $value)) {
            $this->_usedProperties['grault'] = true;
            $this->grault = $value['grault'];
            unset($value['grault']);
        }

        $this->_extraKeys = $value;

    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['corge'])) {
            $output['corge'] = $this->corge;
        }
        if (isset($this->_usedProperties['grault'])) {
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
        $this->_extraKeys[$key] = $value;

        return $this;
    }

}
