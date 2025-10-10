<?php

namespace Symfony\Config\ArrayValues;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ErrorPagesConfig 
{
    private $enabled;
    private $withTrace;
    private $_usedProperties = [];

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enabled($value): static
    {
        $this->_usedProperties['enabled'] = true;
        $this->enabled = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function withTrace($value): static
    {
        $this->_usedProperties['withTrace'] = true;
        $this->withTrace = $value;

        return $this;
    }

    /**
     * @param array{ // Default: {"enabled":false}
     *     enabled?: bool, // Default: false
     *     with_trace?: bool,
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('enabled', $config)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $config['enabled'];
            unset($config['enabled']);
        }

        if (array_key_exists('with_trace', $config)) {
            $this->_usedProperties['withTrace'] = true;
            $this->withTrace = $config['with_trace'];
            unset($config['with_trace']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['enabled'])) {
            $output['enabled'] = $this->enabled;
        }
        if (isset($this->_usedProperties['withTrace'])) {
            $output['with_trace'] = $this->withTrace;
        }

        return $output;
    }

}
