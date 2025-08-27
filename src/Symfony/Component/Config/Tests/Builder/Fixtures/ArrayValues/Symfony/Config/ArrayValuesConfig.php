<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayValues'.\DIRECTORY_SEPARATOR.'TransportsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayValues'.\DIRECTORY_SEPARATOR.'ErrorPagesConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayValues'.\DIRECTORY_SEPARATOR.'PlacesConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ArrayValuesConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $transports;
    private $errorPages;
    private $places;
    private $_usedProperties = [];

    /**
     * @template TValue of string|array
     * @param TValue $value
     * @return \Symfony\Config\ArrayValues\TransportsConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\ArrayValues\TransportsConfig : static)
     */
    public function transports(string $name, string|array $value = []): \Symfony\Config\ArrayValues\TransportsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports[$name] = $value;

            return $this;
        }

        if (!isset($this->transports[$name]) || !$this->transports[$name] instanceof \Symfony\Config\ArrayValues\TransportsConfig) {
            $this->_usedProperties['transports'] = true;
            $this->transports[$name] = new \Symfony\Config\ArrayValues\TransportsConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "transports()" has already been initialized. You cannot pass values the second time you call transports().');
        }

        return $this->transports[$name];
    }

    /**
     * @default {"enabled":false}
    */
    public function errorPages(array $value = []): \Symfony\Config\ArrayValues\ErrorPagesConfig
    {
        if (null === $this->errorPages) {
            $this->_usedProperties['errorPages'] = true;
            $this->errorPages = new \Symfony\Config\ArrayValues\ErrorPagesConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "errorPages()" has already been initialized. You cannot pass values the second time you call errorPages().');
        }

        return $this->errorPages;
    }

    public function places(array $value = []): \Symfony\Config\ArrayValues\PlacesConfig
    {
        $this->_usedProperties['places'] = true;

        return $this->places[] = new \Symfony\Config\ArrayValues\PlacesConfig($value);
    }

    public function getExtensionAlias(): string
    {
        return 'array_values';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('transports', $value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports = array_map(fn ($v) => \is_array($v) ? new \Symfony\Config\ArrayValues\TransportsConfig($v) : $v, $value['transports']);
            unset($value['transports']);
        }

        if (array_key_exists('error_pages', $value)) {
            $this->_usedProperties['errorPages'] = true;
            $this->errorPages = \is_array($value['error_pages']) ? new \Symfony\Config\ArrayValues\ErrorPagesConfig($value['error_pages']) : $value['error_pages'];
            unset($value['error_pages']);
        }

        if (array_key_exists('places', $value)) {
            $this->_usedProperties['places'] = true;
            $this->places = array_map(fn ($v) => new \Symfony\Config\ArrayValues\PlacesConfig($v), $value['places']);
            unset($value['places']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['transports'])) {
            $output['transports'] = array_map(fn ($v) => $v instanceof \Symfony\Config\ArrayValues\TransportsConfig ? $v->toArray() : $v, $this->transports);
        }
        if (isset($this->_usedProperties['errorPages'])) {
            $output['error_pages'] = $this->errorPages instanceof \Symfony\Config\ArrayValues\ErrorPagesConfig ? $this->errorPages->toArray() : $this->errorPages;
        }
        if (isset($this->_usedProperties['places'])) {
            $output['places'] = array_map(fn ($v) => $v->toArray(), $this->places);
        }

        return $output;
    }

}
