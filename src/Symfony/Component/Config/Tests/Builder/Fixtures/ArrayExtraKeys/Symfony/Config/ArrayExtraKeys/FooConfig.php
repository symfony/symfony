<?php

namespace Symfony\Config\ArrayExtraKeys;

use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class FooConfig 
{
    private $baz;
    private $qux;
    private $_usedProperties = [];
    private $_extraKeys;

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function baz($value): static
    {
        $this->_usedProperties['baz'] = true;
        $this->baz = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function qux($value): static
    {
        $this->_usedProperties['qux'] = true;
        $this->qux = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('baz', $value)) {
            $this->_usedProperties['baz'] = true;
            $this->baz = $value['baz'];
            unset($value['baz']);
        }

        if (array_key_exists('qux', $value)) {
            $this->_usedProperties['qux'] = true;
            $this->qux = $value['qux'];
            unset($value['qux']);
        }

        $this->_extraKeys = $value;

    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['baz'])) {
            $output['baz'] = $this->baz;
        }
        if (isset($this->_usedProperties['qux'])) {
            $output['qux'] = $this->qux;
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
