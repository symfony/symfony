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
class DayOfYearTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $dayOfYear = $dateTime->format('z') + 1;
        return $this->padLeft($dayOfYear, $length);
    }

    public function getReverseMatchingRegExp($length)
    {
        return "\d{$length}";
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'hour' => (int) $matched,
        );
    }
}
