<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents the main properties of a PHP variable, pre-casted by a caster.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CasterStub extends Stub
{
    public function __construct($value, $class = '')
    {
        switch (gettype($value)) {
            case 'object':
                $this->type = self::TYPE_OBJECT;
                $this->class = get_class($value);
                $this->value = spl_object_hash($value);
                $this->cut = -1;
                break;

            case 'array':
                $this->type = self::TYPE_ARRAY;
                $this->class = self::ARRAY_ASSOC;
                $this->cut = $this->value = count($value);
                break;

            case 'resource':
            case 'unknown type':
                $this->type = self::TYPE_RESOURCE;
                $this->class = @get_resource_type($value);
                $this->value = (int) $value;
                $this->cut = -1;
                break;

            case 'string':
                if ('' === $class) {
                    $this->type = self::TYPE_STRING;
                    $this->class = preg_match('//u', $value) ? self::STRING_UTF8 : self::STRING_BINARY;
                    $this->cut = self::STRING_BINARY === $this->class ? strlen($value) : (function_exists('iconv_strlen') ? iconv_strlen($value, 'UTF-8') : -1);
                    break;
                }
                // No break;

            default:
                $this->class = $class;
                $this->value = $value;
                break;
        }
    }
}
