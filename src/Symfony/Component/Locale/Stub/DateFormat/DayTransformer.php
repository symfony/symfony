<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class DayTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        return $this->padLeft($dateTime->format('j'), $length);
    }

    public function getReverseMatchingRegExp($length)
    {
        if (1 == $length) {
            $regExp = '\d{1,2}';
        } else {
            $regExp = '\d{'.$length.'}';
        }

        return $regExp;
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'day' => (int) $matched,
        );
    }
}
