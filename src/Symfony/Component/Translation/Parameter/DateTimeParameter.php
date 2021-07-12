<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Parameter;

use Symfony\Contracts\Translation\ParameterInterface;

/**
 * Wrapper around PHP IntlDateFormatter for date and time
 * The timezone from the DateTime instance is used instead of the server's timezone.
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
class DateTimeParameter implements ParameterInterface
{
    private $dateTime;
    private $dateType;
    private $timeType;

    private $formatters = [];

    public function __construct(
        \DateTimeInterface $dateTime,
        int $dateType = \IntlDateFormatter::SHORT,
        int $timeType = \IntlDateFormatter::SHORT
    ) {
        $this->dateTime = $dateTime;
        $this->dateType = $dateType;
        $this->timeType = $timeType;
    }

    public function format(string $locale = null): string
    {
        $timezone = $this->dateTime->getTimezone();
        $key = implode('.', [$locale, $this->dateType, $this->timeType, $timezone->getName()]);
        if (!isset($this->formatters[$key])) {
            $this->formatters[$key] = new \IntlDateFormatter(
                $locale,
                $this->dateType,
                $this->timeType,
                $timezone
            );
        }

        return $this->formatters[$key]->format($this->dateTime);
    }

    /**
     * Short-hand to only format a date.
     */
    public static function date(\DateTimeInterface $dateTime, int $type = \IntlDateFormatter::SHORT): self
    {
        return new self($dateTime, $type, \IntlDateFormatter::NONE);
    }

    /**
     * Short-hand to only format a time.
     */
    public static function time(\DateTimeInterface $dateTime, int $type = \IntlDateFormatter::SHORT): self
    {
        return new self($dateTime, \IntlDateFormatter::NONE, $type);
    }
}
