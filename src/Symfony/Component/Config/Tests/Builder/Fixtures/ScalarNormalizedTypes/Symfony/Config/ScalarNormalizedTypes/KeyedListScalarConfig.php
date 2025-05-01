<?php

namespace Symfony\Config\ScalarNormalizedTypes;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class KeyedListScalarConfig 
{
    private $list;
    private $_usedProperties = [];

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function list(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['list'] = true;
        $this->list = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('list', $value)) {
            $this->_usedProperties['list'] = true;
            $this->list = $value['list'];
            unset($value['list']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['list'])) {
            $output['list'] = $this->list;
        }

        return $output;
    }

}
