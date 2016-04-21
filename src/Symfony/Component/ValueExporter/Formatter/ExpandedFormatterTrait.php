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

use Symfony\Component\ValueExporter\Exporter\ValueExporterInterface;

/**
 * ExpandedFormatter.
 *
 * A trait holding the {@link ValueExporterInterface} to export nested values.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
trait ExpandedFormatterTrait
{
    /**
     * @var ValueExporterInterface
     */
    private $exporter;

    /**
     * Sets the exporter to call on nested values.
     *
     * @param ValueExporterInterface $exporter The exporter
     */
    final public function setExporter(ValueExporterInterface $exporter)
    {
        $this->exporter = $exporter;
    }

    /**
     * @param mixed $value The nested value to export
     *
     * @return mixed The exported nested value
     */
    final protected function export($value)
    {
        return $this->exporter->exportValue($value);
    }
}
