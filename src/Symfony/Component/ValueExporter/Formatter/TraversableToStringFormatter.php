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
 * Returns a string representation of an instance implementing \Traversable.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class TraversableToStringFormatter implements StringFormatterInterface
{
    use ExpandedFormatterTrait;

    /**
     * {@inheritdoc}
     */
    public function supports($value)
    {
        return $value instanceof \Traversable;
    }

    /**
     * {@inheritdoc}
     */
    public function formatToString($value)
    {
        $nested = array();
        foreach ($value as $k => $v) {
            $nested[] = sprintf('%s => %s', is_string($k) ? sprintf("'%s'", $k) : $k, $this->export($v));
        }

        return sprintf("Traversable:\"%s\"(\n  %s\n)", get_class($value), implode(",\n  ", $nested));
    }
}
