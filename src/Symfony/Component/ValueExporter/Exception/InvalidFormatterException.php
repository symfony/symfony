<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Exception;

/**
 * Thrown when a {@link \Symfony\Component\ValueExporter\Formatter\FormatterInterface}
 * is not supported by the {@link \Symfony\Component\ValueExporter\Exporter\ValueExporterInterface}.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class InvalidFormatterException extends \InvalidArgumentException
{
    /**
     * @param string $formatterClass    The invalid formatter class
     * @param string $exporterClass     The exporter class
     * @param string $expectedInterface The expected formatter interface
     */
    public function __construct($formatterClass, $exporterClass, $expectedInterface)
    {
        parent::__construct(sprintf('The exporter "%s" expects formatters implementing "%", but was given "%s" class.', $exporterClass, $expectedInterface, $formatterClass));
    }
}
