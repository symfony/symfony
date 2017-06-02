<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Formatter;

/**
 * Returns a string representation of a DateTimeInterface instance.
 *
 * Based on the contribution by @scuben (https://github.com/scuben)
 * https://github.com/symfony/symfony/commit/a1762fb65423dc94d69c5fb6abaed37f2ad576e6
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class DateTimeToStringFormatter implements StringFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports($value)
    {
        return $value instanceof \DateTimeInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function formatToString($value)
    {
        return sprintf('object(%s) - %s', get_class($value), $value->format(\DateTime::ISO8601));
    }
}
