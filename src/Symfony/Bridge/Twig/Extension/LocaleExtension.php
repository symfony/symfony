<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Locale\Formatter;

/**
 * @author Eriksen Costa <eriksencosta@gmail.com>
 */
class LocaleExtension extends \Twig_Extension
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * Constructor.
     */
    public function __construct(Formatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'formatCurrency' => new \Twig_Filter_Method($this, 'formatCurrency'),
            'formatDate'     => new \Twig_Filter_Method($this, 'formatDate'),
            'formatTime'     => new \Twig_Filter_Method($this, 'formatTime'),
            'formatDateTime' => new \Twig_Filter_Method($this, 'formatDateTime'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'locale';
    }

    /**
     * Formats a value with the desired currency.
     *
     * @param float   $value
     * @param string  $currency
     * @param string  $locale
     *
     * @return string
     *
     * @see Formatter::formatCurrency()
     */
    public function formatCurrency($value, $currency = null, $locale = null)
    {
        return $this->formatter->formatCurrency($value, $currency);
    }

    /**
     * Formats a date.
     *
     * @param mixed   $date
     * @param int     $dateStyle
     * @param string  $timezone
     * @param int     $calendar
     * @param string  $pattern
     * @param string  $locale
     *
     * @see Formatter::formatDate()
     *
     * @return string
     */
    public function formatDate($date, $dateStyle = null, $timezone = null, $calendar = null, $pattern = null, $locale = null)
    {
        return $this->formatter->formatDate($date, $dateStyle, $timezone, $calendar, $pattern, $locale);
    }

    /**
     * Formats a time.
     *
     * @param mixed   $time
     * @param int     $dateStyle
     * @param string  $timezone
     * @param int     $calendar
     * @param string  $pattern
     * @param string  $locale
     *
     * @return string
     *
     * @see Formatter::formatTime()
     */
    public function formatTime($time, $timeStyle = null, $timezone = null, $calendar = null, $pattern = null, $locale = null)
    {
        return $this->formatter->formatTime($time, $timeStyle, $timezone, $calendar, $pattern, $locale);
    }

    /**
     * Formats a date/time.
     *
     * @param mixed   $dateTime
     * @param int     $dateStyle
     * @param string  $timezone
     * @param int     $calendar
     * @param string  $pattern
     * @param string  $locale
     *
     * @return string
     *
     * @see Formatter::formatDateTime()
     */
    public function formatDateTime($dateTime, $dateStyle = null, $timeStyle = null, $timezone = null, $calendar = null, $pattern = null, $locale = null)
    {
        return $this->formatter->formatDateTime($dateTime, $dateStyle, $timeStyle, $timezone, $calendar, $pattern, $locale);
    }
}
