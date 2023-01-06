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

    public function __construct(array $value = [])
    {
        if (array_key_exists('priority', $value)) {
            $this->_usedProperties['priority'] = true;
            $this->priority = $value['priority'];
            unset($value['priority']);
        }

        if (array_key_exists('color', $value)) {
            $this->_usedProperties['color'] = true;
            $this->color = $value['color'];
            unset($value['color']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
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
