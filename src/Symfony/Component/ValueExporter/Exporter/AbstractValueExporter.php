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
use Symfony\Component\ValueExporter\Formatter\ExpandedFormatter;
use Symfony\Component\ValueExporter\Formatter\FormatterInterface;

/**
 * ValueExporterInterface implementations export PHP values.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class AbstractValueExporter implements ValueExporterInterface
{
    /**
     * The supported formatter interface.
     *
     * @var string
     */
    protected $formatterInterface = FormatterInterface::class;

    /**
     * An array of formatters.
     *
     * @var FormatterInterface[]
     */
    private $formatters = array();

    /**
     * Takes {@link FormatterInterface} as arguments.
     *
     * They will be called in the given order.
     */
    final public function __construct()
    {
        $this->addFormatters(func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    final public function addFormatters(array $appends, array $prepends = array())
    {
        foreach ($appends as $append) {
            if (!$append instanceof $this->formatterInterface) {
                throw new InvalidFormatterException(get_class($append), self::class, $this->formatterInterface);
            }
            if ($append instanceof ExpandedFormatter) {
                $append->setExporter($this);
            }
            $this->formatters[] = $append;
        }
        foreach (array_reverse($prepends) as $prepend) {
            if (!$prepend instanceof $this->formatterInterface) {
                throw new InvalidFormatterException(get_class($prepend), self::class, $this->formatterInterface);
            }
            if ($prepend instanceof ExpandedFormatter) {
                $prepend->setExporter($this);
            }
            array_unshift($this->formatters, $prepend);
        }
    }

    /**
     * @return FormatterInterface[]
     */
    final protected function formatters()
    {
        return $this->formatters;
    }
}
