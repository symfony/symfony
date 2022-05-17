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

    public function __construct(array $value = [])
    {
        if (array_key_exists('number', $value)) {
            $this->_usedProperties['number'] = true;
            $this->number = $value['number'];
            unset($value['number']);
        }

        if (array_key_exists('content', $value)) {
            $this->_usedProperties['content'] = true;
            $this->content = $value['content'];
            unset($value['content']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
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
