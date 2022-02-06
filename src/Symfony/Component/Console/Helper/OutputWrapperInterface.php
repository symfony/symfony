<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

interface OutputWrapperInterface
{
    public const TAG_OPEN_REGEX_SEGMENT = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
    public const TAG_CLOSE_REGEX_SEGMENT = '[a-z][^<>]*+';

    /**
     * @param positive-int|0 $width
     */
    public function wrap(string $text, int $width, string $break = "\n"): string;
}
