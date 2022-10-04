<?php

namespace Symfony\Config\AddToList;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'RoutingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'ReceivingConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MessengerConfig 
{
    private $routing;
    private $receiving;
    private $_usedProperties = [];

    public function routing(string $message_class, array $value = []): \Symfony\Config\AddToList\Messenger\RoutingConfig
    {
        if (!isset($this->routing[$message_class])) {
            $this->_usedProperties['routing'] = true;
            $this->routing[$message_class] = new \Symfony\Config\AddToList\Messenger\RoutingConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "routing()" has already been initialized. You cannot pass values the second time you call routing().');
        }

        return $this->routing[$message_class];
    }

    public function receiving(array $value = []): \Symfony\Config\AddToList\Messenger\ReceivingConfig
    {
        $this->_usedProperties['receiving'] = true;

        return $this->receiving[] = new \Symfony\Config\AddToList\Messenger\ReceivingConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('routing', $value)) {
            $this->_usedProperties['routing'] = true;
            $this->routing = array_map(function ($v) { return new \Symfony\Config\AddToList\Messenger\RoutingConfig($v); }, $value['routing']);
            unset($value['routing']);
        }

        if (array_key_exists('receiving', $value)) {
            $this->_usedProperties['receiving'] = true;
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
        if (isset($this->_usedProperties['routing'])) {
            $output['routing'] = array_map(function ($v) { return $v->toArray(); }, $this->routing);
        }
        if (isset($this->_usedProperties['receiving'])) {
            $output['receiving'] = array_map(function ($v) { return $v->toArray(); }, $this->receiving);
        }

        return $output;
    }

}
