<?php

namespace Symfony\Config\AddToList\Translator;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Books'.\DIRECTORY_SEPARATOR.'PageConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class BooksConfig 
{
    private $page;
    private $_usedProperties = [];

    /**
     * @example "page 1"
     * @default {"number":1,"content":""}
    */
    public function page(array $value = []): \Symfony\Config\AddToList\Translator\Books\PageConfig
    {
        $this->_usedProperties['page'] = true;

        return $this->page[] = new \Symfony\Config\AddToList\Translator\Books\PageConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('page', $value)) {
            $this->_usedProperties['page'] = true;
            $this->page = array_map(fn ($v) => new \Symfony\Config\AddToList\Translator\Books\PageConfig($v), $value['page']);
            unset($value['page']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['page'])) {
            $output['page'] = array_map(fn ($v) => $v->toArray(), $this->page);
        }

        return $output;
    }

}
