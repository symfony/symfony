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

use Symfony\Component\HttpFoundation\Header\Accept;
use Symfony\Component\HttpFoundation\Header\AcceptItem;

/**
 * Represents an Accept-* header.
 *
 * An accept header is compound with a list of items,
 * sorted by descending quality.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\HttpFoundation\Header\Accept instead.
 */
class AcceptHeader extends Accept
{
    public static function fromString($headerValue)
    {
        $index = 0;

        return new self(array_map(function ($itemValue) use (&$index) {
            $item = AcceptItem::fromString($itemValue);
            $item->setIndex($index++);

            return $item;
        }, preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $headerValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)));
    }

}
