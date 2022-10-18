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

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('date_format', $value)) {
            $this->_usedProperties['dateFormat'] = true;
            $this->dateFormat = $value['date_format'];
            unset($value['date_format']);
        }

        if (array_key_exists('remove_used_context_fields', $value)) {
            $this->_usedProperties['removeUsedContextFields'] = true;
            $this->removeUsedContextFields = $value['remove_used_context_fields'];
            unset($value['remove_used_context_fields']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
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
