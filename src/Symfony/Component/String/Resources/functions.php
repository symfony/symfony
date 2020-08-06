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

use Symfony\Component\String\Slugger\AsciiSlugger;

function u(string $string = ''): UnicodeString
{
    return new UnicodeString($string);
}

function b(string $string = ''): ByteString
{
    return new ByteString($string);
}

/**
 * @return UnicodeString|ByteString
 */
function s(string $string): AbstractString
{
    return preg_match('//u', $string) ? new UnicodeString($string) : new ByteString($string);
}

function slugify(string $string, string $separator = '-', string $locale = null): AbstractUnicodeString
{
    return (new AsciiSlugger())->slug($string, $separator, $locale);
}