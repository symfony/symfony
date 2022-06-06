<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter\DateFormat;

/**
 * Base class for hour transformers.
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
abstract class HourTransformer extends Transformer
{
    /**
     * Returns a normalized hour value suitable for the hour transformer type.
     *
     * @param int    $hour   The hour value
     * @param string $marker An optional AM/PM marker
     *
     * @return int The normalized hour value
     */
    abstract public function normalizeHour(int $hour, string $marker = null): int;
}
