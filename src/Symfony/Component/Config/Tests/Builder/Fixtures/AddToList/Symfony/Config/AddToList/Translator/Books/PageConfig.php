<?php

namespace Symfony\Config\AddToList\Translator\Books;


use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class PageConfig 
{
    private $number;
    private $content;
    
    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function number($value): static
    {
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
        $this->content = $value;
    
        return $this;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['number'])) {
            $this->number = $value['number'];
            unset($value['number']);
        }
    
        if (isset($value['content'])) {
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
        if (null !== $this->number) {
            $output['number'] = $this->number;
        }
        if (null !== $this->content) {
            $output['content'] = $this->content;
        }
    
        return $output;
    }

}
