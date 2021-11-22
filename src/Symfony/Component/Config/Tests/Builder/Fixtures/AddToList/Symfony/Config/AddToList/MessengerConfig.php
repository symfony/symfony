<?php

namespace Symfony\Config\AddToList;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'RoutingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'ReceivingConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class MessengerConfig 
{
    private $routing;
    private $receiving;
    
    public function routing(string $message_class, array $value = []): \Symfony\Config\AddToList\Messenger\RoutingConfig
    {
        if (!isset($this->routing[$message_class])) {
            return $this->routing[$message_class] = new \Symfony\Config\AddToList\Messenger\RoutingConfig($value);
        }
        if ([] === $value) {
            return $this->routing[$message_class];
        }
    
        throw new InvalidConfigurationException('The node created by "routing()" has already been initialized. You cannot pass values the second time you call routing().');
    }
    
    public function receiving(array $value = []): \Symfony\Config\AddToList\Messenger\ReceivingConfig
    {
        return $this->receiving[] = new \Symfony\Config\AddToList\Messenger\ReceivingConfig($value);
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['routing'])) {
            $this->routing = array_map(function ($v) { return new \Symfony\Config\AddToList\Messenger\RoutingConfig($v); }, $value['routing']);
            unset($value['routing']);
        }
    
        if (isset($value['receiving'])) {
            $this->receiving = array_map(function ($v) { return new \Symfony\Config\AddToList\Messenger\ReceivingConfig($v); }, $value['receiving']);
            unset($value['receiving']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->routing) {
            $output['routing'] = array_map(function ($v) { return $v->toArray(); }, $this->routing);
        }
        if (null !== $this->receiving) {
            $output['receiving'] = array_map(function ($v) { return $v->toArray(); }, $this->receiving);
        }
    
        return $output;
    }

}
