<?php

namespace Symfony\Config\ArrayExtraKeys;

use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class BazConfig 
{
    private $_extraKeys;

    public function __construct(array $value = [])
    {
        $this->_extraKeys = $value;

    }

    public function toArray(): array
    {
        $output = [];

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
