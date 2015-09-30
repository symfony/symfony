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

@trigger_error('The '.__NAMESPACE__.'\TwigDefaultEscapingStrategy class is deprecated in version 2.7 and will be removed in version 3.0. Use the "filename" auto-escaping strategy instead.', E_USER_DEPRECATED);

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.7, will be removed in 3.0. Use the "filename" auto-escaping strategy instead.
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
