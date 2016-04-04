<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter;

use Symfony\Component\ValueExporter\Exporter\ValueExporterInterface;
use Symfony\Component\ValueExporter\Exporter\ValueToStringExporter;
use Symfony\Component\ValueExporter\Formatter\DateTimeToStringFormatter;
use Symfony\Component\ValueExporter\Formatter\FormatterInterface;
use Symfony\Component\ValueExporter\Formatter\PhpIncompleteClassToStringFormatter;

// Load the global to_string() function
require_once __DIR__.'/Resources/functions/to_string.php';

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class ValueExporter
{
    private static $handler;
    private static $exporter;
    private static $appends = array();
    private static $prepends = array();

    public static function export($value, $depth = 1, $expand = false)
    {
        if (null === self::$handler) {
            $exporter = self::$exporter ?: new ValueToStringExporter(
                new DateTimeToStringFormatter(),
                new PhpIncompleteClassToStringFormatter()
            );
            $exporter->addFormatters(self::$appends, self::$prepends);
            // Clear extra formatters
            self::$appends = self::$prepends = array();
            self::$handler = function ($value, $depth = 1, $expand = false) use ($exporter) {
                return $exporter->exportValue($value, $depth, $expand);
            };
        }

        return call_user_func(self::$handler, $value, $depth, $expand);
    }

    public static function setHandler(callable $callable = null)
    {
        $prevHandler = self::$handler;
        self::$handler = $callable;

        return $prevHandler;
    }

    /**
     * Sets a new {@link ValueExporterInterface} instance as exporter.
     *
     * @param ValueExporterInterface $exporter The exporter instance
     */
    public static function setExporter(ValueExporterInterface $exporter)
    {
        self::$handler = null;
        self::$exporter = $exporter;
        self::$appends = self:: $prepends = array();
    }

    /**
     * Appends a {@link FormatterInterface} to the {@link ValueExporterInterface}.
     *
     * @param FormatterInterface $formatter
     */
    public static function appendFormatter(FormatterInterface $formatter)
    {
        self::$handler = null;
        self::$appends[] = $formatter;
    }

    /**
     * Prepends a {@link FormatterInterface} to the {@link ValueExporterInterface}.
     *
     * @param FormatterInterface $formatter
     */
    public static function prependFormatter(FormatterInterface $formatter)
    {
        self::$handler = null;
        self::$prepends[] = $formatter;
    }
}
