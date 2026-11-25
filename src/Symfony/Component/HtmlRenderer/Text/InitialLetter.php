<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

/** @internal */
final class InitialLetter
{
    /** @return array{string,string,string}|null */
    public static function split(string $text): ?array
    {
        return preg_match('/^(\s*)(\p{P}*\X)(.*)$/su', $text, $matches) ? [$matches[1], $matches[2], $matches[3]] : null;
    }
}
