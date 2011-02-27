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
class YearTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        if (2 == $length) {
            return $dateTime->format('y');
        } else {
            return $this->padLeft($dateTime->format('Y'), $length);
        }
    }

    public function getReverseMatchingRegExp($length)
    {
        if (2 == $length) {
            return '?P<yy>\d{2}';
        } else {
            $length = $length < 4 ? 4 : $length;
            return "?P<y>\d{1,$length}";
        }
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'year' => (int) $matched,
        );
    }
}
