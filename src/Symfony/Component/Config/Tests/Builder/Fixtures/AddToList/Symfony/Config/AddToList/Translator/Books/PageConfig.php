<?php

namespace Symfony\Config\AddToList\Translator\Books;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PageConfig 
{
    private $number;
    private $content;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function number($value): static
    {
        $this->_usedProperties['number'] = true;
        $this->number = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function content($value): static
    {
        $this->_usedProperties['content'] = true;
        $this->content = $value;

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
        if (array_key_exists('number', $config)) {
            $this->_usedProperties['number'] = true;
            $this->number = $config['number'];
            unset($config['number']);
        }

        if (array_key_exists('content', $config)) {
            $this->_usedProperties['content'] = true;
            $this->content = $config['content'];
            unset($config['content']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['number'])) {
            $output['number'] = $this->number;
        }
        if (isset($this->_usedProperties['content'])) {
            $output['content'] = $this->content;
        }

        return $output;
    }

}
