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

    public function __construct(array $value = [])
    {
        if (array_key_exists('senders', $value)) {
            $this->_usedProperties['senders'] = true;
            $this->senders = $value['senders'];
            unset($value['senders']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
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
