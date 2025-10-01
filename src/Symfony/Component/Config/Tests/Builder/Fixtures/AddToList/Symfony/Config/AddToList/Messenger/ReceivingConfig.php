<?php

namespace Symfony\Config\AddToList\Messenger;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ReceivingConfig 
{
    private $priority;
    private $color;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function priority($value): static
    {
        $this->_usedProperties['priority'] = true;
        $this->priority = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function color($value): static
    {
        $this->_usedProperties['color'] = true;
        $this->color = $value;

        return $this;
    }

    /**
     * @param array{
     *     translator?: array{
     *         fallbacks?: list<scalar|null>,
     *         sources?: array<string, scalar|null>,
     *         books?: array{ // Deprecated: The child node "books" at path "add_to_list.translator.books" is deprecated. // looks for translation in old fashion way
     *             page?: list<array{
     *                 number?: int<min, max>,
     *                 content?: scalar|null,
     *             }>,
     *         },
     *     },
     *     messenger?: array{
     *         routing?: array<string, array{
     *             senders?: list<scalar|null>,
     *         }>,
     *         receiving?: list<array{
     *             priority?: int<min, max>,
     *             color?: scalar|null,
     *         }>,
     *     },
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('priority', $config)) {
            $this->_usedProperties['priority'] = true;
            $this->priority = $config['priority'];
            unset($config['priority']);
        }

        if (array_key_exists('color', $config)) {
            $this->_usedProperties['color'] = true;
            $this->color = $config['color'];
            unset($config['color']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['priority'])) {
            $output['priority'] = $this->priority;
        }
        if (isset($this->_usedProperties['color'])) {
            $output['color'] = $this->color;
        }

        return $output;
    }

}
