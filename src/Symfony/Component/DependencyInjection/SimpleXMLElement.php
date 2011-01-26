<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * SimpleXMLElement class.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SimpleXMLElement extends \SimpleXMLElement
{
    public function getAttributeAsPhp($name)
    {
        return self::phpize($this[$name]);
    }

    public function getArgumentsAsPhp($name)
    {
        $arguments = array();
        foreach ($this->$name as $arg) {
            $key = isset($arg['key']) ? (string) $arg['key'] : (!$arguments ? 0 : max(array_keys($arguments)) + 1);

            // parameter keys are case insensitive
            if ('parameter' == $name) {
                $key = strtolower($key);
            }

            // this is used by DefinitionDecorator to overwrite a specific
            // argument of the parent definition
            if (isset($arg['index'])) {
                $key = 'index_'.$arg['index'];
            }

            switch ($arg['type']) {
                case 'service':
                    $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                    if (isset($arg['on-invalid']) && 'ignore' == $arg['on-invalid']) {
                        $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
                    } elseif (isset($arg['on-invalid']) && 'null' == $arg['on-invalid']) {
                        $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                    }

                    if (isset($arg['strict'])) {
                        $strict = self::phpize($arg['strict']);
                    } else {
                        $strict = true;
                    }

                    $arguments[$key] = new Reference((string) $arg['id'], $invalidBehavior, $strict);
                    break;
                case 'collection':
                    $arguments[$key] = $arg->getArgumentsAsPhp($name);
                    break;
                case 'string':
                    $arguments[$key] = (string) $arg;
                    break;
                case 'constant':
                    $arguments[$key] = constant((string) $arg);
                    break;
                default:
                    $arguments[$key] = self::phpize($arg);
            }
        }

        return $arguments;
    }

    static public function phpize($value)
    {
        $value = (string) $value;
        $lowercaseValue = strtolower($value);

        switch (true) {
            case 'null' === $lowercaseValue:
                return null;
            case ctype_digit($value):
                return '0' == $value[0] ? octdec($value) : intval($value);
            case 'true' === $lowercaseValue:
                return true;
            case 'false' === $lowercaseValue:
                return false;
            case is_numeric($value):
                return '0x' == $value[0].$value[1] ? hexdec($value) : floatval($value);
            case preg_match('/^(-|\+)?[0-9,]+(\.[0-9]+)?$/', $value):
                return floatval(str_replace(',', '', $value));
            default:
                return $value;
        }
    }
}
