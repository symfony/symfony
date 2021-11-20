<?php

namespace Symfony\Config\AddToList;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Translator'.\DIRECTORY_SEPARATOR.'BooksConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class TranslatorConfig 
{
    private $fallbacks;
    private $sources;
    private $books;
    private $_usedProperties = [];

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function fallbacks(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['fallbacks'] = true;
        $this->fallbacks = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function source(string $source_class, mixed $value): static
    {
        $this->_usedProperties['sources'] = true;
        $this->sources[$source_class] = $value;

        return $this;
    }

    /**
     * @template TValue
     * @param TValue $value
     * looks for translation in old fashion way
     * @deprecated The child node "books" at path "translator" is deprecated.
     * @return \Symfony\Config\AddToList\Translator\BooksConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\AddToList\Translator\BooksConfig : static)
     */
    public function books(mixed $value = []): \Symfony\Config\AddToList\Translator\BooksConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['books'] = true;
            $this->books = $value;

            return $this;
        }

        if (!$this->books instanceof \Symfony\Config\AddToList\Translator\BooksConfig) {
            $this->_usedProperties['books'] = true;
            $this->books = new \Symfony\Config\AddToList\Translator\BooksConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "books()" has already been initialized. You cannot pass values the second time you call books().');
        }

        return $this->books;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('fallbacks', $value)) {
            $this->_usedProperties['fallbacks'] = true;
            $this->fallbacks = $value['fallbacks'];
            unset($value['fallbacks']);
        }

        if (array_key_exists('sources', $value)) {
            $this->_usedProperties['sources'] = true;
            $this->sources = $value['sources'];
            unset($value['sources']);
        }

        if (array_key_exists('books', $value)) {
            $this->_usedProperties['books'] = true;
            $this->books = \is_array($value['books']) ? new \Symfony\Config\AddToList\Translator\BooksConfig($value['books']) : $value['books'];
            unset($value['books']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['fallbacks'])) {
            $output['fallbacks'] = $this->fallbacks;
        }
        if (isset($this->_usedProperties['sources'])) {
            $output['sources'] = $this->sources;
        }
        if (isset($this->_usedProperties['books'])) {
            $output['books'] = $this->books instanceof \Symfony\Config\AddToList\Translator\BooksConfig ? $this->books->toArray() : $this->books;
        }

        return $output;
    }

}
