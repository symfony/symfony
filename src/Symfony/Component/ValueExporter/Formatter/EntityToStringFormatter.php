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
class EntityToStringFormatter implements StringFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports($value)
    {
        return is_object($value)
               && !$value instanceof \Closure
               && (isset($value->id) || is_callable(array($value, 'id')) || is_callable(array($value, 'getId')))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function formatToString($value)
    {
        $id = isset($value->id) ? $value->id : (is_callable(array($value, 'id')) ? $value->id() : $value->getId());

        if (method_exists($value, '__toString')) {
            return sprintf('entity:%s(%s) "%s"', $id, get_class($value), $value);
        }

        return sprintf('entity:%s(%s)', $id, get_class($value));
    }
}
