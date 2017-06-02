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
use Symfony\Component\ValueExporter\Formatter\ExpandedFormatterTrait;
use Symfony\Component\ValueExporter\Formatter\FormatterInterface;

/**
 * ValueExporterInterface implementations export PHP values.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class AbstractValueExporter implements ValueExporterInterface
{
    /**
     * @var int
     */
    protected $depth;
    /**
     * @var bool
     */
    protected $expand;

    /**
     * The supported formatter interface.
     *
     * @var string
     */
    protected $formatterInterface = FormatterInterface::class;

    /**
     * An array indexed by formatter FQCN with a corresponding priority as value.
     *
     * @var int[]
     */
    private $formatters = array();

    /**
     * An array of formatters instances sorted by priority or null.
     *
     * @var FormatterInterface[]|null
     */
    private $sortedFormatters;

    /**
     * An array of cached formatters instances by their FQCN.
     *
     * @var FormatterInterface[]
     */
    private $cachedFormatters = array();

    /**
     * Takes {@link FormatterInterface} FQCN as arguments.
     *
     * They will be called in the given order.
     * Alternatively, instead of a class, you can pass an array with
     * a class and its priority {@see self::addFormatters}.
     */
    final public function __construct()
    {
        $this->addFormatters(func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    final public function addFormatters(array $formatters)
    {
        $this->sortedFormatters = null;

        foreach ($formatters as $formatter) {
            if (is_array($formatter)) {
                $priority = (int) $formatter[1];
                $formatterClass = $formatter[0];
            } else {
                $priority = 0;
                $formatterClass = $formatter;
            }

            if (!in_array($this->formatterInterface, class_implements($formatterClass), true)) {
                throw new InvalidFormatterException($formatterClass, static::class, $this->formatterInterface);
            }

            // Using the class as key prevents duplicate and allows to
            // dynamically change the priority
            $this->formatters[$formatterClass] = $priority;
        }
    }

    /**
     * @return FormatterInterface[]
     */
    final protected function formatters()
    {
        if (null === $this->sortedFormatters) {
            arsort($this->formatters);

            foreach (array_keys($this->formatters) as $formatterClass) {
                if (isset($this->cachedFormatters[$formatterClass])) {
                    $this->sortedFormatters[] = $this->cachedFormatters[$formatterClass];

                    continue;
                }

                $formatter = new $formatterClass();

                if (in_array(ExpandedFormatterTrait::class, class_uses($formatterClass), true)) {
                    /* @var ExpandedFormatterTrait $formatter */
                    $formatter->setExporter($this);
                }

                $this->sortedFormatters[] = $this->cachedFormatters[$formatterClass] = $formatter;
            }
        }

        return $this->sortedFormatters;
    }
}
