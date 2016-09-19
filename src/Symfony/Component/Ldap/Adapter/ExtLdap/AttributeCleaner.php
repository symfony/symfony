<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter\ExtLdap;

/**
 * Common trait used to filter LDAP attributes.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
trait AttributeCleaner
{
    private function cleanupAttributes(array $entry)
    {
        $attributes = array_diff_key($entry, array_flip(range(0, $entry['count'] - 1)) + array(
                'count' => null,
                'dn' => null,
            ));
        array_walk($attributes, function (&$value) {
            unset($value['count']);
        });

        return $attributes;
    }
}
