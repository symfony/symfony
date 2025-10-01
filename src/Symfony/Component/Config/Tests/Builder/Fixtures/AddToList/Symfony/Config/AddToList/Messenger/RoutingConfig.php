<?php

namespace Symfony\Config\AddToList\Messenger;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RoutingConfig 
{
    private $senders;
    private $_usedProperties = [];

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function senders(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['senders'] = true;
        $this->senders = $value;

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
        if (array_key_exists('senders', $config)) {
            $this->_usedProperties['senders'] = true;
            $this->senders = $config['senders'];
            unset($config['senders']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['senders'])) {
            $output['senders'] = $this->senders;
        }

        return $output;
    }

}
