<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Charset;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Day;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Encoding;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Strategy;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Udh;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Alan Zarli <azarli@smsbox.fr>
 * @author Farid Touil <ftouil@smsbox.fr>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class SmsboxOptions implements MessageOptionsInterface
{
    private ClockInterface $clock;

    public function __construct(
        private array $options = [],
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? Clock::get();
    }

    public function getRecipientId(): null
    {
        return null;
    }

    /**
     * @return $this
     */
    public function mode(Mode $mode): static
    {
        $this->options['mode'] = $mode->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function strategy(Strategy $strategy): static
    {
        $this->options['strategy'] = $strategy->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function date(string $date): static
    {
        if (isset($this->options['dateTime'])) {
            throw new InvalidArgumentException(\sprintf('Either %1$s::dateTime() or %1$s::date() and %1$s::hour() must be called, but not both.', self::class));
        }

        if (!\DateTimeImmutable::createFromFormat('d/m/Y', $date)) {
            throw new \DateMalformedStringException('The date must be in DD/MM/YYYY format.');
        }

        $this->options['date'] = $date;

        return $this;
    }

    /**
     * @return $this
     */
    public function hour(string $hour): static
    {
        if (isset($this->options['dateTime'])) {
            throw new InvalidArgumentException(\sprintf('Either %1$s::dateTime() or %1$s::date() and %1$s::hour() must be called, but not both.', self::class));
        }

        if (!\DateTimeImmutable::createFromFormat('H:i', $hour)) {
            throw new \DateMalformedStringException('Hour must be in HH:MM format.');
        }

        $this->options['heure'] = $hour;

        return $this;
    }

    /**
     * @return $this
     */
    public function dateTime(\DateTimeImmutable $dateTime): static
    {
        if (isset($this->options['date']) || isset($this->options['heure'])) {
            throw new InvalidArgumentException(\sprintf('Either %1$s::dateTime() or %1$s::date() and %1$s::hour() must be called, but not both.', self::class));
        }

        if ($dateTime < $this->clock->now()) {
            throw new InvalidArgumentException('The given DateTime must be greater to the current date.');
        }

        $this->options['dateTime'] = $dateTime->setTimezone(new \DateTimeZone('Europe/Paris'));

        return $this;
    }

    /**
     * An ISO 3166-1 alpha code.
     *
     * @return $this
     */
    public function destIso(string $isoCode): static
    {
        if (class_exists(Countries::class) && !Countries::exists($isoCode)) {
            throw new InvalidArgumentException(\sprintf('The country code "%s" is not valid.', $isoCode));
        }

        $this->options['dest_iso'] = $isoCode;

        return $this;
    }

    /**
     * Automatically sets `personnalise` option to 1.
     *
     * @return $this
     */
    public function variable(array $variable): static
    {
        $this->options['variable'] = $variable;

        return $this;
    }

    /**
     * @return $this
     */
    public function coding(Encoding $encoding): static
    {
        $this->options['coding'] = $encoding->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function charset(Charset $charset): static
    {
        $this->options['charset'] = $charset->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function udh(Udh $udh): static
    {
        $this->options['udh'] = $udh->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function callback(bool $callback): static
    {
        $this->options['callback'] = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    public function allowVocal(bool $allowVocal): static
    {
        $this->options['allow_vocal'] = $allowVocal;

        return $this;
    }

    /**
     * @return $this
     */
    public function maxParts(int $maxParts): static
    {
        if ($maxParts < 1 || $maxParts > 8) {
            throw new InvalidArgumentException(\sprintf('The "max_parts" option must be an integer between 1 and 8, got "%d".', $maxParts));
        }

        $this->options['max_parts'] = $maxParts;

        return $this;
    }

    /**
     * @return $this
     */
    public function validity(int $validity): static
    {
        if ($validity < 5 || $validity > 1440) {
            throw new InvalidArgumentException(\sprintf('The "validity" option must be an integer between 5 and 1440, got "%d".', $validity));
        }

        $this->options['validity'] = $validity;

        return $this;
    }

    /**
     * @return $this
     */
    public function daysMinMax(Day $min, Day $max): static
    {
        if (!$min->isBeforeOrEqualTo($max)) {
            throw new InvalidArgumentException('The minimum day must be before the maximum day or the same.');
        }

        $this->options['daysMinMax'] = [$min->value, $max->value];

        return $this;
    }

    /**
     * @return $this
     */
    public function hoursMinMax(int $min, int $max): static
    {
        if ($min < 0 || $min > $max) {
            throw new InvalidArgumentException('The minimum hour must be greater than 0 and lower than the maximum hour.');
        }

        if ($max > 23) {
            throw new InvalidArgumentException('The maximum hour must be lower or equal to 23.');
        }

        $this->options['hoursMinMax'] = [$min, $max];

        return $this;
    }

    /**
     * @return $this
     */
    public function sender(string $sender): static
    {
        $this->options['sender'] = $sender;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
