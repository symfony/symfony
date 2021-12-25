<?php

namespace Symfony\Config;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class PlaceholdersConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $enabled;
    private $favoriteFloat;
    private $goodIntegers;
    
    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enabled($value): static
    {
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
        $this->goodIntegers = $value;
    
        return $this;
    }
    
    public function getExtensionAlias(): string
    {
        return 'placeholders';
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['enabled'])) {
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }
    
        if (isset($value['favorite_float'])) {
            $this->favoriteFloat = $value['favorite_float'];
            unset($value['favorite_float']);
        }
    
        if (isset($value['good_integers'])) {
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
        if (null !== $this->enabled) {
            $output['enabled'] = $this->enabled;
        }
        if (null !== $this->favoriteFloat) {
            $output['favorite_float'] = $this->favoriteFloat;
        }
        if (null !== $this->goodIntegers) {
            $output['good_integers'] = $this->goodIntegers;
        }
    
        return $output;
    }

}
