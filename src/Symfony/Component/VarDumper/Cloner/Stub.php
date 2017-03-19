<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

/**
 * Represents the main properties of a PHP variable.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Stub
{
    const TYPE_REF = 'ref';
    const TYPE_STRING = 'string';
    const TYPE_ARRAY = 'array';
    const TYPE_OBJECT = 'object';
    const TYPE_RESOURCE = 'resource';

    const STRING_BINARY = 'bin';
    const STRING_UTF8 = 'utf8';

    const ARRAY_ASSOC = 'assoc';
    const ARRAY_INDEXED = 'indexed';

    public $type = self::TYPE_REF;
    public $class = '';
    public $value;
    public $cut = 0;
    public $handle = 0;
    public $refCount = 0;
    public $position = 0;
    public $attr = array();
}
