<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'NodeInitialValues'.\DIRECTORY_SEPARATOR.'SomeCleverNameConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'NodeInitialValues'.\DIRECTORY_SEPARATOR.'MessengerConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class NodeInitialValuesConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $someCleverName;
    private $messenger;
    private $_usedProperties = [];

    public function someCleverName(array $value = []): \Symfony\Config\NodeInitialValues\SomeCleverNameConfig
    {
        if (null === $this->someCleverName) {
            $this->_usedProperties['someCleverName'] = true;
            $this->someCleverName = new \Symfony\Config\NodeInitialValues\SomeCleverNameConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "someCleverName()" has already been initialized. You cannot pass values the second time you call someCleverName().');
        }

        return $this->someCleverName;
    }

    public function messenger(array $value = []): \Symfony\Config\NodeInitialValues\MessengerConfig
    {
        if (null === $this->messenger) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\NodeInitialValues\MessengerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "messenger()" has already been initialized. You cannot pass values the second time you call messenger().');
        }

        return $this->messenger;
    }

    public function getExtensionAlias(): string
    {
        return 'node_initial_values';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('some_clever_name', $value)) {
            $this->_usedProperties['someCleverName'] = true;
            $this->someCleverName = new \Symfony\Config\NodeInitialValues\SomeCleverNameConfig($value['some_clever_name']);
            unset($value['some_clever_name']);
        }

        if (array_key_exists('messenger', $value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\NodeInitialValues\MessengerConfig($value['messenger']);
            unset($value['messenger']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['someCleverName'])) {
            $output['some_clever_name'] = $this->someCleverName->toArray();
        }
        if (isset($this->_usedProperties['messenger'])) {
            $output['messenger'] = $this->messenger->toArray();
        }

        return $output;
    }

}
