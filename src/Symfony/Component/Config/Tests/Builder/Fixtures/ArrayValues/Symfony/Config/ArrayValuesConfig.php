<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayValues'.\DIRECTORY_SEPARATOR.'TransportsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayValues'.\DIRECTORY_SEPARATOR.'ErrorPagesConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ArrayValuesConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $transports;
    private $errorPages;
    private $_usedProperties = [];
    private $_hasDeprecatedCalls = false;

    /**
     * @template TValue of string|array
     * @param TValue $value
     * @return \Symfony\Config\ArrayValues\TransportsConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\ArrayValues\TransportsConfig : static)
     * @deprecated since Symfony 7.4
     */
    public function transports(string $name, string|array $value = []): \Symfony\Config\ArrayValues\TransportsConfig|static
    {
        $this->_hasDeprecatedCalls = true;
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
     * @template TValue of array|bool
     * @param TValue $value
     * @default {"enabled":false}
     * @return \Symfony\Config\ArrayValues\ErrorPagesConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\ArrayValues\ErrorPagesConfig : static)
     * @deprecated since Symfony 7.4
     */
    public function errorPages(array|bool $value = []): \Symfony\Config\ArrayValues\ErrorPagesConfig|static
    {
        $this->_hasDeprecatedCalls = true;
        if (!\is_array($value)) {
            $this->_usedProperties['errorPages'] = true;
            $this->errorPages = $value;

            return $this;
        }

        if (!$this->errorPages instanceof \Symfony\Config\ArrayValues\ErrorPagesConfig) {
            $this->_usedProperties['errorPages'] = true;
            $this->errorPages = new \Symfony\Config\ArrayValues\ErrorPagesConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "errorPages()" has already been initialized. You cannot pass values the second time you call errorPages().');
        }

        return $this->errorPages;
    }

    public function getExtensionAlias(): string
    {
        return 'array_values';
    }

    public function __construct(array $config = [])
    {
        if (array_key_exists('transports', $config)) {
            $this->_usedProperties['transports'] = true;
            $this->transports = array_map(fn ($v) => \is_array($v) ? new \Symfony\Config\ArrayValues\TransportsConfig($v) : $v, $config['transports']);
            unset($config['transports']);
        }

        if (array_key_exists('error_pages', $config)) {
            $this->_usedProperties['errorPages'] = true;
            $this->errorPages = \is_array($config['error_pages']) ? new \Symfony\Config\ArrayValues\ErrorPagesConfig($config['error_pages']) : $config['error_pages'];
            unset($config['error_pages']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
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
        if ($this->_hasDeprecatedCalls) {
            trigger_deprecation('symfony/config', '7.4', 'Calling any fluent method on "%s" is deprecated; pass the configuration to the constructor instead.', $this::class);
        }

        return $output;
    }

}
