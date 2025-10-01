<?php

namespace Symfony\Config\ArrayExtraKeys;

use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class FooConfig 
{
    private $baz;
    private $qux;
    private $_usedProperties = [];
    private $_extraKeys;

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function baz($value): static
    {
        $this->_usedProperties['baz'] = true;
        $this->baz = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function qux($value): static
    {
        $this->_usedProperties['qux'] = true;
        $this->qux = $value;

        return $this;
    }

    /**
     * @param array{
     *     foo?: array{
     *         baz?: scalar|null,
     *         qux?: scalar|null,
     *         ...<mixed>
     *     },
     *     bar?: list<array{
     *         corge?: scalar|null,
     *         grault?: scalar|null,
     *         ...<mixed>
     *     }>,
     *     baz?: array<mixed>,
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('baz', $config)) {
            $this->_usedProperties['baz'] = true;
            $this->baz = $config['baz'];
            unset($config['baz']);
        }

        if (array_key_exists('qux', $config)) {
            $this->_usedProperties['qux'] = true;
            $this->qux = $config['qux'];
            unset($config['qux']);
        }

        $this->_extraKeys = $config;

    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['baz'])) {
            $output['baz'] = $this->baz;
        }
        if (isset($this->_usedProperties['qux'])) {
            $output['qux'] = $this->qux;
        }

        return $output + $this->_extraKeys;
    }

    /**
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function set(string $key, mixed $value): static
    {
        $this->_extraKeys[$key] = $value;

        return $this;
    }

}
