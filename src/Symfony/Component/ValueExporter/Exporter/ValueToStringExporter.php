<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Exporter;

use Symfony\Component\ValueExporter\Formatter\StringFormatterInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Quentin Schuler <qschuler@neosyne.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class ValueToStringExporter extends AbstractValueExporter
{
    protected $formatterInterface = StringFormatterInterface::class;

    public function exportValue($value, $depth = 1, $expand = false)
    {
        // Use set properties for recursive calls
        $depth = null === $this->depth ? $depth : $this->depth;
        $expand = null === $this->expand ? $expand : $this->expand;
        // Arrays have to be handled first to deal with nested level and depth,
        // this implementation intentionally ignores \Traversable values.
        // Therefor, \Traversable instances might be treated as objects unless
        // implementing a {@link StringFormatterInterface} and passing it to
        // the exporter in order to support them.
        if (is_array($value) && !is_callable($value)) {
            if (empty($value)) {
                return 'array()';
            }
            $indent = str_repeat('  ', $depth);

            $a = array();
            foreach ($value as $k => $v) {
                if (is_array($v) && !empty($v)) {
                    $this->expand = true;
                    $this->depth = $depth + 1;
                }
                $a[] = sprintf('%s => %s', is_string($k) ? sprintf("'%s'", $k) : $k, $this->exportValue($v));
                $this->depth = null;
                $this->expand = null;
            }
            if ($expand) {
                return sprintf("array(\n%s%s\n%s)", $indent, implode(sprintf(", \n%s", $indent), $a), str_repeat('  ', $depth - 1));
            }

            $s = sprintf('array(%s)', implode(', ', $a));

            if (80 > strlen($s)) {
                return $s;
            }

            return sprintf("array(\n%s%s\n)", $indent, implode(sprintf(",\n%s", $indent), $a));
        }
        // Not an array, test each formatter
        foreach ($this->formatters() as $formatter) {
            /** @var StringFormatterInterface $formatter */
            if ($formatter->supports($value)) {
                return $formatter->formatToString($value);
            }
        }
        // Fallback on default
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return sprintf('object(%s) "%s"', get_class($value), $value);
            }

            return sprintf('object(%s)', get_class($value));
        }
        if (is_resource($value)) {
            return sprintf('resource(%s#%d)', get_resource_type($value), $value);
        }
        if (is_float($value)) {
            return sprintf('(float) %s', $value);
        }
        if (is_int($value)) {
            return sprintf('(int) %d', $value);
        }
        if (is_string($value)) {
            return sprintf('"%s"', $value);
        }
        if (null === $value) {
            return 'null';
        }
        if (false === $value) {
            return 'false';
        }
        if (true === $value) {
            return 'true';
        }

        return (string) $value;
    }
}
