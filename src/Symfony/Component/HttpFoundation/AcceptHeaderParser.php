<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Parses Accept-* HTTP headers.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class AcceptHeaderParser
{
    /**
     * Splits an Accept-* HTTP header.
     *
     * @param string $header Header to split
     *
     * @return array Array indexed by the values of the Accept-* header in preferred order
     */
    public function split($header)
    {
        if (!$header) {
            return array();
        }

        $values = array();
        $groups = array();
        foreach (array_filter(explode(',', $header)) as $value) {
            // Cut off any q-value that might come after a semi-colon
            if (preg_match('/;\s*(q=.*$)/', $value, $match)) {
                $q     = substr(trim($match[1]), 2);
                $value = trim(substr($value, 0, -strlen($match[0])));
            } else {
                $q = 1;
            }

            $groups[$q][] = $value;
        }

        krsort($groups);

        foreach ($groups as $q => $items) {
            $q = (float) $q;

            if (0 < $q) {
                foreach ($items as $value) {
                    $values[trim($value)] = $q;
                }
            }
        }

        return $values;
    }
}
