<?php

namespace Symfony\Config\NodeInitialValues;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'TransportsConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MessengerConfig 
{
    private $transports;
    private $_usedProperties = [];

    public function transports(string $name, array $value = []): \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig
    {
        if (!isset($this->transports[$name])) {
            $this->_usedProperties['transports'] = true;
            $this->transports[$name] = new \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "transports()" has already been initialized. You cannot pass values the second time you call transports().');
        }

        return $this->transports[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('transports', $value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports = array_map(fn ($v) => new \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig($v), $value['transports']);
            unset($value['transports']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['transports'])) {
            $output['transports'] = array_map(fn ($v) => $v->toArray(), $this->transports);
        }

        return $output;
    }

}
