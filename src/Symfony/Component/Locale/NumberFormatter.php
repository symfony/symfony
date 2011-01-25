<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\NumberFormatterInterface;

/**
 * Provides a NumberFormatter using the related intl class capabilities.
 */
class NumberFormatter implements NumberFormatterInterface
{
    private $formatter = null;

    /**
     * @{inheritDoc}
     */
    public function __construct($locale, $style, $pattern = null)
    {
        $this->formatter = new \NumberFormatter($locale, $style, $pattern);
    }

    /**
     * @{inheritDoc}
     */
    public function formatCurrency($value, $currency)
    {
        return $this->formatter->formatCurrency($value, $currency);
    }

    /**
     * @{inheritDoc}
     */
    public function format($value, $type = null)
    {
        return $this->formatter->format($value, $type);
    }

    /**
     * @{inheritDoc}
     */
    public function getAttribute($attr)
    {
        return $this->formatter->getAttribute($attr);
    }

    /**
     * @{inheritDoc}
     */
    public function getErrorCode()
    {
        return $this->formatter->getErrorCode();
    }

    /**
     * @{inheritDoc}
     */
    public function getErrorMessage()
    {
        return $this->formatter->getErrorMessage();
    }

    /**
     * @{inheritDoc}
     */
    public function getLocale($type = Locale::ACTUAL_LOCALE)
    {
        return $this->formatter->getLocale($type);
    }

    /**
     * @{inheritDoc}
     */
    public function getPattern()
    {
        return $this->formatter->getPattern();
    }

    /**
     * @{inheritDoc}
     */
    public function getSymbol($attr)
    {
        return $this->formatter->getSymbol($attr);
    }

    /**
     * @{inheritDoc}
     */
    public function getTextAttribute($attr)
    {
        return $this->formatter->getTextAttribute($attr);
    }

    /**
     * @{inheritDoc}
     */
    public function parseCurrency($value, &$currency, &$position = null)
    {
        return $this->formatter->parseCurrency($value, $currency, $position);
    }

    /**
     * @{inheritDoc}
     */
    public function parse($value, $type = self::TYPE_DOUBLE, &$position = null)
    {
        return $this->formatter->parse($value, $type, $position);
    }

    /**
     * @{inheritDoc}
     */
    public function setAttribute($attr, $value)
    {
        return $this->formatter->setAttribute($attr, $value);
    }

    /**
     * @{inheritDoc}
     */
    public function setPattern($pattern)
    {
        return $this->formatter->setPattern($pattern);
    }

    /**
     * @{inheritDoc}
     */
    public function setSymbol($attr, $value)
    {
        return $this->formatter->setSymbol($attr, $value);
    }

    /**
     * @{inheritDoc}
     */
    public function setTextAttribute($attr, $value)
    {
        return $this->formatter->setTextAttribute($attr, $value);
    }
}
