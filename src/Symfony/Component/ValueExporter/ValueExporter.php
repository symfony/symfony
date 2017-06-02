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
use Symfony\Component\ValueExporter\Formatter\CallableToStringFormatter;
use Symfony\Component\ValueExporter\Formatter\DateTimeToStringFormatter;
use Symfony\Component\ValueExporter\Formatter\EntityToStringFormatter;
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
    private static $formatters = array();

    public static function export($value, $depth = 1, $expand = false)
    {
        if (null === self::$handler) {
            $exporter = self::$exporter ?: new ValueToStringExporter(
                CallableToStringFormatter::class,
                DateTimeToStringFormatter::class,
                EntityToStringFormatter::class,
                PhpIncompleteClassToStringFormatter::class
            );
            $exporter->addFormatters(self::$formatters);
            // Clear formatters
            self::$formatters = array();
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
        self::$formatters = array();
    }

    /**
     * Adds {@link FormatterInterface} to the {@link ValueExporterInterface}.
     *
     * You can simple pass an instance or an array with the instance and the priority:
     *
     * <code>
     * ValueExporter::addFormatters(array(
     *     new AcmeFormatter,
     *     array(new AcmeOtherFormatter(), 10)
     * );
     * </code>
     *
     * @param mixed $formatters An array of FormatterInterface instances and/or
     *                          arrays holding an instance and its priority
     */
    public static function addFormatters($formatters)
    {
        self::$handler = null;
        foreach ($formatters as $formatter) {
            self::$formatters[] = $formatter;
        }
    }
}
