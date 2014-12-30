<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigDefaultEscapingStrategy
{
    public static function guess($filename)
    {
        // remove .twig
        $filename = substr($filename, 0, -5);

        // get the format
        $format = substr($filename, strrpos($filename, '.') + 1);

        if ('js' === $format) {
            return 'js';
        }

        if ('txt' === $format) {
            return false;
        }

        return 'html';
    }
}
