<?php

namespace Symfony\Config;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class VariableTypeConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $anyValue;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function anyValue(mixed $value): static
    {
        $this->_usedProperties['anyValue'] = true;
        $this->anyValue = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'variable_type';
    }

    /**
     * @param array{
     *     any_value?: mixed,
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('any_value', $config)) {
            $this->_usedProperties['anyValue'] = true;
            $this->anyValue = $config['any_value'];
            unset($config['any_value']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['anyValue'])) {
            $output['any_value'] = $this->anyValue;
        }

        return $output;
    }

}
