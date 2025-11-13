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
    private $_hasDeprecatedCalls = false;

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     * @deprecated since Symfony 7.4
     */
    public function enabled($value): static
    {
        $this->_hasDeprecatedCalls = true;
        $this->_usedProperties['enabled'] = true;
        $this->enabled = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|float $value
     * @return $this
     * @deprecated since Symfony 7.4
     */
    public function favoriteFloat($value): static
    {
        $this->_hasDeprecatedCalls = true;
        $this->_usedProperties['favoriteFloat'] = true;
        $this->favoriteFloat = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|int> $value
     *
     * @return $this
     * @deprecated since Symfony 7.4
     */
    public function goodIntegers(ParamConfigurator|array $value): static
    {
        $this->_hasDeprecatedCalls = true;
        $this->_usedProperties['goodIntegers'] = true;
        $this->goodIntegers = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'placeholders';
    }

    public function __construct(array $config = [])
    {
        if (array_key_exists('enabled', $config)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $config['enabled'];
            unset($config['enabled']);
        }

        if (array_key_exists('favorite_float', $config)) {
            $this->_usedProperties['favoriteFloat'] = true;
            $this->favoriteFloat = $config['favorite_float'];
            unset($config['favorite_float']);
        }

        if (array_key_exists('good_integers', $config)) {
            $this->_usedProperties['goodIntegers'] = true;
            $this->goodIntegers = $config['good_integers'];
            unset($config['good_integers']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
        if ($this->_hasDeprecatedCalls) {
            trigger_deprecation('symfony/config', '7.4', 'Calling any fluent method on "%s" is deprecated; pass the configuration to the constructor instead.', $this::class);
        }

        return $output;
    }

}
