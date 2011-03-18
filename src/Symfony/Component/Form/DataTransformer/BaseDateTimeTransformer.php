<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataTransformer;

use Symfony\Component\Form\Configurable;

abstract class BaseDateTimeTransformer extends Configurable implements DataTransformerInterface
{
    protected static $formats = array(
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    );
}