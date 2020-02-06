<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String;

function u(string $string = ''): UnicodeString
{
    return new UnicodeString($string);
}

function b(string $string = ''): ByteString
{
    return new ByteString($string);
}
