<?php

namespace Symfony\Config\ScalarNormalizedTypes\Nested;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class NestedListObjectConfig 
{
    private $name;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function name($value): static
    {
        $this->_usedProperties['name'] = true;
        $this->name = $value;

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
        if (array_key_exists('name', $config)) {
            $this->_usedProperties['name'] = true;
            $this->name = $config['name'];
            unset($config['name']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['name'])) {
            $output['name'] = $this->name;
        }

        return $output;
    }

}
