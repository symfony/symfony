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

    /**
     * @param array{
     *     some_clever_name?: array{
     *         first?: scalar|null,
     *         second?: scalar|null,
     *         third?: scalar|null,
     *     },
     *     messenger?: array{
     *         transports?: array<string, array{
     *             dsn?: scalar|null, // The DSN to use. This is a required option. The info is used to describe the DSN, it can be multi-line.
     *             serializer?: scalar|null, // Default: null
     *             options?: list<mixed>,
     *         }>,
     *     },
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('first', $config)) {
            $this->_usedProperties['first'] = true;
            $this->first = $config['first'];
            unset($config['first']);
        }

        if (array_key_exists('second', $config)) {
            $this->_usedProperties['second'] = true;
            $this->second = $config['second'];
            unset($config['second']);
        }

        if (array_key_exists('third', $config)) {
            $this->_usedProperties['third'] = true;
            $this->third = $config['third'];
            unset($config['third']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
