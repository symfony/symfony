<?php

namespace Symfony\Config\AddToList;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Translator'.\DIRECTORY_SEPARATOR.'BooksConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


/**
 * This class is automatically generated to help creating config.
 */
class TranslatorConfig 
{
    private $fallbacks;
    private $sources;
    private $books;
    
    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function fallbacks(ParamConfigurator|array $value): static
    {
        $this->fallbacks = $value;
    
        return $this;
    }
    
    /**
     * @return $this
     */
    public function source(string $source_class, mixed $value): static
    {
        $this->sources[$source_class] = $value;
    
        return $this;
    }
    
    /**
     * looks for translation in old fashion way
     * @deprecated The child node "books" at path "translator" is deprecated.
    */
    public function books(array $value = []): \Symfony\Config\AddToList\Translator\BooksConfig
    {
        if (null === $this->books) {
            $this->books = new \Symfony\Config\AddToList\Translator\BooksConfig($value);
        } elseif ([] !== $value) {
            throw new InvalidConfigurationException('The node created by "books()" has already been initialized. You cannot pass values the second time you call books().');
        }
    
        return $this->books;
    }
    
    public function __construct(array $value = [])
    {
    
        if (isset($value['fallbacks'])) {
            $this->fallbacks = $value['fallbacks'];
            unset($value['fallbacks']);
        }
    
        if (isset($value['sources'])) {
            $this->sources = $value['sources'];
            unset($value['sources']);
        }
    
        if (isset($value['books'])) {
            $this->books = new \Symfony\Config\AddToList\Translator\BooksConfig($value['books']);
            unset($value['books']);
        }
    
        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }
    
    public function toArray(): array
    {
        $output = [];
        if (null !== $this->fallbacks) {
            $output['fallbacks'] = $this->fallbacks;
        }
        if (null !== $this->sources) {
            $output['sources'] = $this->sources;
        }
        if (null !== $this->books) {
            $output['books'] = $this->books->toArray();
        }
    
        return $output;
    }

}
