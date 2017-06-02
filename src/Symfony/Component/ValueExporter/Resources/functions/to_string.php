<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\ValueExporter\ValueExporter;

if (!function_exists('to_string')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     * @author Jules Pietri <jules@heahprod.com>
     */
    function to_string($value, $depth = 1, $expand = false)
    {
        return ValueExporter::export($value, $depth, $expand);
    }
}
