<?php

namespace Symfony\Config\NodeInitialValues;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'TransportsConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class MessengerConfig 
{
    private $transports;
    
    public function transports(string $name, array $value = []): \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig
    {
        if (!isset($this->transports[$name])) {
            return $this->transports[$name] = new \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig($value);
        }
        if ([] === $value) {
            return $this->transports[$name];
        }
    
        throw new InvalidConfigurationException('The node created by "transports()" has already been initialized. You cannot pass values the second time you call transports().');
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['transports'])) {
            $this->transports = array_map(function ($v) { return new \Symfony\Config\NodeInitialValues\Messenger\TransportsConfig($v); }, $value['transports']);
            unset($value['transports']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->transports) {
            $output['transports'] = array_map(function ($v) { return $v->toArray(); }, $this->transports);
        }
    
        return $output;
    }

}
