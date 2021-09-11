<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Translatable;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wrapper around PHP IntlDateFormatter for date and time
 * The timezone from the DateTime instance is used instead of the server's timezone.
 *
 * Implementation of the ICU recommendation to first format advanced parameters before translation.
 *
 * @see https://unicode-org.github.io/icu/userguide/format_parse/messages/#format-the-parameters-separately-recommended
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
class DateTimeTranslatable implements TranslatableInterface
{
    private \DateTimeInterface $dateTime;
    private int $dateType;
    private int $timeType;

    private static array $formatters = [];

    public function __construct(
        \DateTimeInterface $dateTime,
        int $dateType = \IntlDateFormatter::SHORT,
        int $timeType = \IntlDateFormatter::SHORT
    ) {
        $this->dateTime = $dateTime;
        $this->dateType = $dateType;
        $this->timeType = $timeType;
    }

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        if (!$locale) {
            $locale = $translator->getLocale();
        }

        $timezone = $this->dateTime->getTimezone();
        $key = implode('.', [$locale, $this->dateType, $this->timeType, $timezone->getName()]);
        if (!isset(self::$formatters[$key])) {
            self::$formatters[$key] = new \IntlDateFormatter(
                $locale ?? $translator->getLocale(),
                $this->dateType,
                $this->timeType,
                $timezone
            );
        }

        return self::$formatters[$key]->format($this->dateTime);
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
