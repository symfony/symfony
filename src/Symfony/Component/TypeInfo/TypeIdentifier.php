<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

/**
 * Identifier of a PHP native type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @experimental
 */
enum TypeIdentifier: string
{
    case ARRAY = 'array';
    case BOOL = 'bool';
    case CALLABLE = 'callable';
    case FALSE = 'false';
    case FLOAT = 'float';
    case INT = 'int';
    case ITERABLE = 'iterable';
    case MIXED = 'mixed';
    case NULL = 'null';
    case OBJECT = 'object';
    case RESOURCE = 'resource';
    case STRING = 'string';
    case TRUE = 'true';
    case NEVER = 'never';
    case VOID = 'void';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
