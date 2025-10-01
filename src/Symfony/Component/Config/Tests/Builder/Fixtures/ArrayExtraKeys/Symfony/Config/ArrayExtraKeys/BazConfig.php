<?php

namespace Symfony\Config\ArrayExtraKeys;

use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class BazConfig 
{
    private $_extraKeys;

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
        $this->_extraKeys = $config;

    }

    public function toArray(): array
    {
        $output = [];

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
