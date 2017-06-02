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

use Symfony\Component\ValueExporter\Exception\InvalidFormatterException;
use Symfony\Component\ValueExporter\Exporter;
use Symfony\Component\ValueExporter\Formatter\FormatterInterface;

/**
 * ValueExporterInterface implementations export PHP values.
 *
 * An implementation can rely on {@link FormatterInterface} implementations
 * to handle specific types of value.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
interface ValueExporterInterface
{
    /**
     * Exports a PHP value.
     *
     * ValueExporter instance should always deal with array or \Traversable
     * values first in order to handle depth and expand arguments.
     *
     * Usually you don't need to define the depth but it will be incremented
     * in recursive calls. When expand is false any expandable values such as
     * arrays or objects should be inline in their exported representation.
     *
     * @param mixed $value  The PHP value to export
     * @param int   $depth  The level of indentation
     * @param bool  $expand Whether to inline or expand nested values
     */
    public function exportValue($value, $depth = 1, $expand = false);

    /**
     * Adds {@link FormatterInterface} that will be called by priority.
     *
     * @param (FormatterInterface|array)[] $formatters
     *
     * @throws InvalidFormatterException If the exporter does not support a given formatter
     */
    public function addFormatters(array $formatters);
}
