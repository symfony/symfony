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

    /**
     * @template TValue
     * @param TValue $value
     * @return \Symfony\Config\NodeInitialValues\SomeCleverNameConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\NodeInitialValues\SomeCleverNameConfig : static)
     */
    public function someCleverName(mixed $value = []): \Symfony\Config\NodeInitialValues\SomeCleverNameConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['someCleverName'] = true;
            $this->someCleverName = $value;

            return $this;
        }

        if (!$this->someCleverName instanceof \Symfony\Config\NodeInitialValues\SomeCleverNameConfig) {
            $this->_usedProperties['someCleverName'] = true;
            $this->someCleverName = new \Symfony\Config\NodeInitialValues\SomeCleverNameConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "someCleverName()" has already been initialized. You cannot pass values the second time you call someCleverName().');
        }

        return $this->someCleverName;
    }

    /**
     * @template TValue
     * @param TValue $value
     * @return \Symfony\Config\NodeInitialValues\MessengerConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\NodeInitialValues\MessengerConfig : static)
     */
    public function messenger(mixed $value = []): \Symfony\Config\NodeInitialValues\MessengerConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = $value;

            return $this;
        }

        if (!$this->messenger instanceof \Symfony\Config\NodeInitialValues\MessengerConfig) {
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
            $this->someCleverName = \is_array($value['some_clever_name']) ? new \Symfony\Config\NodeInitialValues\SomeCleverNameConfig($value['some_clever_name']) : $value['some_clever_name'];
            unset($value['some_clever_name']);
        }

        if (array_key_exists('messenger', $value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = \is_array($value['messenger']) ? new \Symfony\Config\NodeInitialValues\MessengerConfig($value['messenger']) : $value['messenger'];
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
            $output['some_clever_name'] = $this->someCleverName instanceof \Symfony\Config\NodeInitialValues\SomeCleverNameConfig ? $this->someCleverName->toArray() : $this->someCleverName;
        }
        if (isset($this->_usedProperties['messenger'])) {
            $output['messenger'] = $this->messenger instanceof \Symfony\Config\NodeInitialValues\MessengerConfig ? $this->messenger->toArray() : $this->messenger;
        }

        return $output;
    }

}
