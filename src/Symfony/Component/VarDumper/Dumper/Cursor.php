<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

/**
 * Represents the current state of a dumper while dumping.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Cursor
{
    const HASH_INDEXED = 'indexed-array';
    const HASH_ASSOC = 'associative-array';
    const HASH_OBJECT = 'object';
    const HASH_RESOURCE = 'resource';

    public $depth = 0;
    public $refIndex = false;
    public $refTo = false;
    public $refIsHard = false;
    public $hashType;
    public $hashKey;
    public $hashIndex = 0;
    public $hashLength = 0;
    public $hashCut = 0;
    public $stop = false;
}
