<?php

namespace Symfony\Config\ScalarNormalizedTypes;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ObjectConfig 
{
    private $enabled;
    private $dateFormat;
    private $removeUsedContextFields;
    private $_usedProperties = [];

    /**
     * @default null
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
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dateFormat($value): static
    {
        $this->_usedProperties['dateFormat'] = true;
        $this->dateFormat = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function removeUsedContextFields($value): static
    {
        $this->_usedProperties['removeUsedContextFields'] = true;
        $this->removeUsedContextFields = $value;

        return $this;
    }

    /**
     * @param array{
     *     simple_array?: list<scalar|null>,
     *     keyed_array?: array<string, list<scalar|null>>,
     *     object?: array{ // Default: {"enabled":null}
     *         enabled?: bool|null, // Default: null
     *         date_format?: scalar|null,
     *         remove_used_context_fields?: bool,
     *     },
     *     list_object: list<array{
     *         name: scalar|null,
     *         data?: list<mixed>,
     *     }>,
     *     keyed_list_object?: array<string, array{
     *         enabled?: bool, // Default: true
     *         settings?: list<scalar|null>,
     *     }>,
     *     nested?: array{
     *         nested_object?: array{ // Default: {"enabled":null}
     *             enabled?: bool|null, // Default: null
     *         },
     *         nested_list_object?: list<array{
     *             name: scalar|null,
     *         }>,
     *     },
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('enabled', $config)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $config['enabled'];
            unset($config['enabled']);
        }

        if (array_key_exists('date_format', $config)) {
            $this->_usedProperties['dateFormat'] = true;
            $this->dateFormat = $config['date_format'];
            unset($config['date_format']);
        }

        if (array_key_exists('remove_used_context_fields', $config)) {
            $this->_usedProperties['removeUsedContextFields'] = true;
            $this->removeUsedContextFields = $config['remove_used_context_fields'];
            unset($config['remove_used_context_fields']);
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
        if (isset($this->_usedProperties['dateFormat'])) {
            $output['date_format'] = $this->dateFormat;
        }
        if (isset($this->_usedProperties['removeUsedContextFields'])) {
            $output['remove_used_context_fields'] = $this->removeUsedContextFields;
        }

        return $output;
    }

}
