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
        if (array_key_exists('some_clever_name', $config)) {
            $this->_usedProperties['someCleverName'] = true;
            $this->someCleverName = new \Symfony\Config\NodeInitialValues\SomeCleverNameConfig($config['some_clever_name']);
            unset($config['some_clever_name']);
        }

        if (array_key_exists('messenger', $config)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\NodeInitialValues\MessengerConfig($config['messenger']);
            unset($config['messenger']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
