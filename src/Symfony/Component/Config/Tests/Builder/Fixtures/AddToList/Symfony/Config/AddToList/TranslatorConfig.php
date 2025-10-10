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
     * looks for translation in old fashion way
     * @deprecated Since symfony/config 6.0: The child node "books" at path "add_to_list.translator" is deprecated.
    */
    public function books(array $value = []): \Symfony\Config\AddToList\Translator\BooksConfig
    {
        if (null === $this->books) {
            $this->_usedProperties['books'] = true;
            $this->books = new \Symfony\Config\AddToList\Translator\BooksConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "books()" has already been initialized. You cannot pass values the second time you call books().');
        }

        return $this->books;
    }

    /**
     * @param array{
     *     fallbacks?: list<scalar|null>,
     *     sources?: array<string, scalar|null>,
     *     books?: array{ // Deprecated: The child node "books" at path "add_to_list.translator.books" is deprecated. // looks for translation in old fashion way
     *         page?: list<array{
     *             number?: int<min, max>,
     *             content?: scalar|null,
     *         }>,
     *     },
     * } $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('fallbacks', $config)) {
            $this->_usedProperties['fallbacks'] = true;
            $this->fallbacks = $config['fallbacks'];
            unset($config['fallbacks']);
        }

        if (array_key_exists('sources', $config)) {
            $this->_usedProperties['sources'] = true;
            $this->sources = $config['sources'];
            unset($config['sources']);
        }

        if (array_key_exists('books', $config)) {
            $this->_usedProperties['books'] = true;
            $this->books = new \Symfony\Config\AddToList\Translator\BooksConfig($config['books']);
            unset($config['books']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
            $output['books'] = $this->books->toArray();
        }

        return $output;
    }

}
