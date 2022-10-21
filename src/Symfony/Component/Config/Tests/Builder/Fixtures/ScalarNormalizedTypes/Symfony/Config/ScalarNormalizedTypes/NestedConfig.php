<?php

namespace Symfony\Config\ScalarNormalizedTypes;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Nested'.\DIRECTORY_SEPARATOR.'NestedObjectConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Nested'.\DIRECTORY_SEPARATOR.'NestedListObjectConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class NestedConfig 
{
    private $nestedObject;
    private $nestedListObject;
    private $_usedProperties = [];

    /**
     * @template TValue
     * @param TValue $value
     * @default {"enabled":null}
     * @return \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig : static)
     */
    public function nestedObject(mixed $value = []): \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['nestedObject'] = true;
            $this->nestedObject = $value;

            return $this;
        }

        if (!$this->nestedObject instanceof \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig) {
            $this->_usedProperties['nestedObject'] = true;
            $this->nestedObject = new \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "nestedObject()" has already been initialized. You cannot pass values the second time you call nestedObject().');
        }

        return $this->nestedObject;
    }

    /**
     * @template TValue
     * @param TValue $value
     * @return \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig : static)
     */
    public function nestedListObject(mixed $value = []): \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig|static
    {
        $this->_usedProperties['nestedListObject'] = true;
        if (!\is_array($value)) {
            $this->nestedListObject[] = $value;

            return $this;
        }

        return $this->nestedListObject[] = new \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('nested_object', $value)) {
            $this->_usedProperties['nestedObject'] = true;
            $this->nestedObject = \is_array($value['nested_object']) ? new \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig($value['nested_object']) : $value['nested_object'];
            unset($value['nested_object']);
        }

        if (array_key_exists('nested_list_object', $value)) {
            $this->_usedProperties['nestedListObject'] = true;
            $this->nestedListObject = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig($v) : $v; }, $value['nested_list_object']);
            unset($value['nested_list_object']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['nestedObject'])) {
            $output['nested_object'] = $this->nestedObject instanceof \Symfony\Config\ScalarNormalizedTypes\Nested\NestedObjectConfig ? $this->nestedObject->toArray() : $this->nestedObject;
        }
        if (isset($this->_usedProperties['nestedListObject'])) {
            $output['nested_list_object'] = array_map(function ($v) { return $v instanceof \Symfony\Config\ScalarNormalizedTypes\Nested\NestedListObjectConfig ? $v->toArray() : $v; }, $this->nestedListObject);
        }

        return $output;
    }

}
