<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Comparator;
use DateTime;
use DateTimeZone;

/**
 * DateCompare compiles date comparisons.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DateComparator extends Comparator
{
    /**
     * Constructor.
     *
     * @param string|array $test A comparison string maybe with timezone
     *
     * @throws \InvalidArgumentException If the test is not understood
     */
    public function __construct($test)
    {
        $timezone = null;
        if (is_array($test)) {
            list($test, $timezone) = $test;
        }

        if (!preg_match('#^\s*(==|!=|[<>]=?|after|since|before|until)?\s*(.+?)\s*$#i', $test, $matches)) {
            throw new \InvalidArgumentException(sprintf('Don\'t understand "%s" as a date test.', $test));
        }

        try {
            $target = $this->getTimeStamp($matches[2], $timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid date.', $matches[2]));
        }

        $operator = isset($matches[1]) ? $matches[1] : '==';
        if ('since' === $operator || 'after' === $operator) {
            $operator = '>';
        }

        if ('until' === $operator || 'before' === $operator) {
            $operator = '<';
        }

        $this->setOperator($operator);
        $this->setTarget($target);
    }

    /**
     * @inheritdoc
     * @param int|array $test A test timestamp maybe with timezone
     */
    public function test($test)
    {
        $timezone = null;
        if (is_array($test)) {
            list($test, $timezone) = $test;
        }

        $test = $this->getTimeStamp(date('Y-m-d H:i:s', $test), $timezone);

        return parent::test($test);
    }

    /**
     * Calculates timestamp based on the time zone
     *
     * @param string $date
     * @param string|\DateTimeZone $timezone
     * @return string
     */
    protected function getTimeStamp($date, $timezone = null)
    {
        if ($timezone) {
            if (!$timezone instanceof DateTimeZone) {
                $timezone = new DateTimeZone($timezone);
            }
            $datetime = new DateTime($date, $timezone);
            $datetime->setTimezone(new DateTimeZone('GMT'));
        } else {
            $datetime = new DateTime($date);
        }

        $timestamp = $datetime->format('U');

        return $timestamp;
    }
}
