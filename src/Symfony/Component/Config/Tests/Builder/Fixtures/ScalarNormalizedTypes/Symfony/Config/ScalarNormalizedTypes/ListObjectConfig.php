<?php

namespace Symfony\Config\ScalarNormalizedTypes;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ListObjectConfig 
{
    private $name;
    private $data;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function name($value): static
    {
        $this->_usedProperties['name'] = true;
        $this->name = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function data(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['data'] = true;
        $this->data = $value;

        return $this;
    }

    /**
     * @param array{
     *     name: scalar|null,
     *     data?: list<mixed>,
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('name', $config)) {
            $this->_usedProperties['name'] = true;
            $this->name = $config['name'];
            unset($config['name']);
        }

        if (array_key_exists('data', $config)) {
            $this->_usedProperties['data'] = true;
            $this->data = $config['data'];
            unset($config['data']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['name'])) {
            $output['name'] = $this->name;
        }
        if (isset($this->_usedProperties['data'])) {
            $output['data'] = $this->data;
        }

        return $output;
    }

}
