<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

/**
 * Base class for hour transformers
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 */
abstract class HourTransformer extends Transformer
{
    /**
     * Returns a normalized hour value suitable for the hour transformer type
     *
     * @param  int     $hour    The hour value
     * @param  string  $marker  An optional AM/PM marker
     * @return int              The normalized hour value
     */
    abstract public function normalizeHour($hour, $marker = null);
}
