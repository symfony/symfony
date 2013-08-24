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
 * Parser and formatter for day format
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @since v2.3.0
 */
class DayTransformer extends Transformer
{
    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function format(\DateTime $dateTime, $length)
    {
        return $this->padLeft($dateTime->format('j'), $length);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getReverseMatchingRegExp($length)
    {
        return 1 === $length ? '\d{1,2}' : '\d{'.$length.'}';
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'day' => (int) $matched,
        );
    }
}
