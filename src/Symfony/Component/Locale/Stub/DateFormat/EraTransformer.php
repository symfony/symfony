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
class EraTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $year = (int) $dateTime->format('Y');
        return $year >= 0 ? 'AD' : 'BC';
    }

    public function getReverseMatchingRegExp($length)
    {
        return "AD|BC";
    }

    public function extractDateOptions($matched, $length)
    {
        return array();
    }
}
