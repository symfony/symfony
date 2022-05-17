<?php

namespace Symfony\Config;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PlaceholdersConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $enabled;
    private $favoriteFloat;
    private $goodIntegers;
    private $_usedProperties = [];

    /**
     * @default false
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
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function favoriteFloat($value): static
    {
        $this->_usedProperties['favoriteFloat'] = true;
        $this->favoriteFloat = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|int> $value
     *
     * @return $this
     */
    public function goodIntegers(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['goodIntegers'] = true;
        $this->goodIntegers = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'placeholders';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('favorite_float', $value)) {
            $this->_usedProperties['favoriteFloat'] = true;
            $this->favoriteFloat = $value['favorite_float'];
            unset($value['favorite_float']);
        }

        if (array_key_exists('good_integers', $value)) {
            $this->_usedProperties['goodIntegers'] = true;
            $this->goodIntegers = $value['good_integers'];
            unset($value['good_integers']);
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
        if (isset($this->_usedProperties['favoriteFloat'])) {
            $output['favorite_float'] = $this->favoriteFloat;
        }
        if (isset($this->_usedProperties['goodIntegers'])) {
            $output['good_integers'] = $this->goodIntegers;
        }

        return $output;
    }

}
