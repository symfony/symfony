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
 * Parser and formatter for AM/PM markers format
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @since v2.3.0
 */
class AmPmTransformer extends Transformer
{
    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function format(\DateTime $dateTime, $length)
    {
        return $dateTime->format('A');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getReverseMatchingRegExp($length)
    {
        return 'AM|PM';
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'marker' => $matched
        );
    }
}
