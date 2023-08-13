<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayExtraKeys'.\DIRECTORY_SEPARATOR.'FooConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayExtraKeys'.\DIRECTORY_SEPARATOR.'BarConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ArrayExtraKeys'.\DIRECTORY_SEPARATOR.'BazConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ArrayExtraKeysConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $foo;
    private $bar;
    private $baz;
    private $_usedProperties = [];

    public function foo(array $value = []): \Symfony\Config\ArrayExtraKeys\FooConfig
    {
        if (null === $this->foo) {
            $this->_usedProperties['foo'] = true;
            $this->foo = new \Symfony\Config\ArrayExtraKeys\FooConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "foo()" has already been initialized. You cannot pass values the second time you call foo().');
        }

        return $this->foo;
    }

    public function bar(array $value = []): \Symfony\Config\ArrayExtraKeys\BarConfig
    {
        $this->_usedProperties['bar'] = true;

        return $this->bar[] = new \Symfony\Config\ArrayExtraKeys\BarConfig($value);
    }

    public function baz(array $value = []): \Symfony\Config\ArrayExtraKeys\BazConfig
    {
        if (null === $this->baz) {
            $this->_usedProperties['baz'] = true;
            $this->baz = new \Symfony\Config\ArrayExtraKeys\BazConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "baz()" has already been initialized. You cannot pass values the second time you call baz().');
        }

        return $this->baz;
    }

    public function getExtensionAlias(): string
    {
        return 'array_extra_keys';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('foo', $value)) {
            $this->_usedProperties['foo'] = true;
            $this->foo = new \Symfony\Config\ArrayExtraKeys\FooConfig($value['foo']);
            unset($value['foo']);
        }

        if (array_key_exists('bar', $value)) {
            $this->_usedProperties['bar'] = true;
            $this->bar = array_map(fn ($v) => new \Symfony\Config\ArrayExtraKeys\BarConfig($v), $value['bar']);
            unset($value['bar']);
        }

        if (array_key_exists('baz', $value)) {
            $this->_usedProperties['baz'] = true;
            $this->baz = new \Symfony\Config\ArrayExtraKeys\BazConfig($value['baz']);
            unset($value['baz']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['foo'])) {
            $output['foo'] = $this->foo->toArray();
        }
        if (isset($this->_usedProperties['bar'])) {
            $output['bar'] = array_map(fn ($v) => $v->toArray(), $this->bar);
        }
        if (isset($this->_usedProperties['baz'])) {
            $output['baz'] = $this->baz->toArray();
        }

        return $output;
    }

}
