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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Alan Zarli <azarli@smsbox.fr>
 * @author Farid Touil <ftouil@smsbox.fr>
 */
final class SmsboxOptions implements MessageOptionsInterface
{
    public const MESSAGE_MODE_STANDARD = 'Standard';
    public const MESSAGE_MODE_EXPERT = 'Expert';
    public const MESSAGE_MODE_RESPONSE = 'Reponse';

    public const MESSAGE_STRATEGY_PRIVATE = 1;
    public const MESSAGE_STRATEGY_NOTIFICATION = 2;
    public const MESSAGE_STRATEGY_NOT_MARKETING_GROUP = 3;
    public const MESSAGE_STRATEGY_MARKETING = 4;

    public const MESSAGE_CODING_DEFAULT = 'default';
    public const MESSAGE_CODING_UNICODE = 'unicode';
    public const MESSAGE_CODING_AUTO = 'auto';

    public const MESSAGE_CHARSET_ISO_1 = 'iso-8859-1';
    public const MESSAGE_CHARSET_ISO_15 = 'iso-8859-15';
    public const MESSAGE_CHARSET_UTF8 = 'utf-8';

    public const MESSAGE_DAYS_MONDAY = 1;
    public const MESSAGE_DAYS_TUESDAY = 2;
    public const MESSAGE_DAYS_WEDNESDAY = 3;
    public const MESSAGE_DAYS_THURSDAY = 4;
    public const MESSAGE_DAYS_FRIDAY = 5;
    public const MESSAGE_DAYS_SATURDAY = 6;
    public const MESSAGE_DAYS_SUNDAY = 7;

    public const MESSAGE_UDH_6_OCTETS = 1;
    public const MESSAGE_UDH_7_OCTETS = 2;
    public const MESSAGE_UDH_DISABLED_CONCAT = 0;

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = [];
    }

    /**
     * @return null
     */
    public function getRecipientId(): ?string
    {
        return null;
    }

    public function mode(string $mode)
    {
        $this->options['mode'] = self::validateMode($mode);

        return $this;
    }

    public function strategy(int $strategy)
    {
        $this->options['strategy'] = self::validateStrategy($strategy);

        return $this;
    }

    public function date(string $date)
    {
        $this->options['date'] = self::validateDate($date);

        return $this;
    }

    public function hour(string $hour)
    {
        $this->options['heure'] = self::validateHour($hour);

        return $this;
    }

    /**
     * This method mustn't be set along with date and hour methods.
     */
    public function dateTime(\DateTime $dateTime)
    {
        $this->options['dateTime'] = self::validateDateTime($dateTime);

        return $this;
    }

    /**
     * This method wait an ISO 3166-1 alpha.
     */
    public function destIso(string $isoCode)
    {
        $this->options['dest_iso'] = self::validateDestIso($isoCode);

        return $this;
    }

    /**
     * This method will automatically set personnalise = 1 (according to SMSBOX documentation).
     */
    public function variable(array $variable)
    {
        $this->options['variable'] = $variable;

        return $this;
    }

    public function coding(string $coding)
    {
        $this->options['coding'] = self::validateCoding($coding);

        return $this;
    }

    public function charset(string $charset)
    {
        $this->options['charset'] = self::validateCharset($charset);

        return $this;
    }

    public function udh(int $udh)
    {
        $this->options['udh'] = self::validateUdh($udh);

        return $this;
    }

    /**
     * The true value = 1 in SMSBOX documentation.
     */
    public function callback(bool $callback)
    {
        $this->options['callback'] = $callback;

        return $this;
    }

    /**
     * The true value = 1 in SMSBOX documentation.
     */
    public function allowVocal(bool $allowVocal)
    {
        $this->options['allow_vocal'] = $allowVocal;

        return $this;
    }

    public function maxParts(int $maxParts)
    {
        $this->options['max_parts'] = self::validateMaxParts($maxParts);

        return $this;
    }

    public function validity(int $validity)
    {
        $this->options['validity'] = self::validateValidity($validity);

        return $this;
    }

    public function daysMinMax(int $min, int $max)
    {
        $this->options['daysMinMax'] = self::validateDays($min, $max);

        return $this;
    }

    public function hoursMinMax(int $min, int $max)
    {
        $this->options['hoursMinMax'] = self::validateHours($min, $max);

        return $this;
    }

    public function sender(string $sender)
    {
        $this->options['sender'] = $sender;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public static function validateMode(string $mode): string
    {
        $supportedModes = [
            self::MESSAGE_MODE_STANDARD,
            self::MESSAGE_MODE_EXPERT,
            self::MESSAGE_MODE_RESPONSE,
        ];

        if (!\in_array($mode, $supportedModes, true)) {
            throw new InvalidArgumentException(sprintf('The message mode "%s" is not supported; supported message modes are: "%s".', $mode, implode('", "', $supportedModes)));
        }

        return $mode;
    }

    public static function validateStrategy(int $strategy): int
    {
        $supportedStrategies = [
            self::MESSAGE_STRATEGY_PRIVATE,
            self::MESSAGE_STRATEGY_NOTIFICATION,
            self::MESSAGE_STRATEGY_NOT_MARKETING_GROUP,
            self::MESSAGE_STRATEGY_MARKETING,
        ];
        if (!\in_array($strategy, $supportedStrategies, true)) {
            throw new InvalidArgumentException(sprintf('The message strategy "%s" is not supported; supported strategies types are: "%s".', $strategy, implode('", "', $supportedStrategies)));
        }

        return $strategy;
    }

    public static function validateDate(string $date): string
    {
        $dateTimeObj = \DateTime::createFromFormat('d/m/Y', $date);
        $now = new \DateTime();
        $tz = new \DateTimeZone('Europe/Paris');
        $dateTimeObj->setTimezone($tz);
        $now->setTimezone($tz);

        if (!$dateTimeObj || $dateTimeObj->format('Y-m-d') <= (new \DateTime())->format('Y-m-d')) {
            throw new InvalidArgumentException('The date must be in DD/MM/YYYY format and greater than the current date.');
        }

        return $date;
    }

    public static function validateDateTime(\DateTime $dateTime)
    {
        \Locale::setDefault('fr');
        $now = new \DateTime();
        $tz = new \DateTimeZone('Europe/Paris');
        $now->setTimezone($tz);
        $dateTime->setTimezone($tz);

        if ($now > $dateTime || $dateTime > $now->modify('+2 Year')) {
            throw new InvalidArgumentException('dateTime must be greater to the actual date and limited to 2 years in the future.');
        }

        return $dateTime;
    }

    public static function validateDestIso(string $isoCode)
    {
        if (!preg_match('/^[a-z]{2}$/i', $isoCode)) {
            throw new InvalidArgumentException('destIso must be the ISO 3166-1 alpha 2 on two uppercase characters.');
        }

        return $isoCode;
    }

    public static function validateHour(string $hour): string
    {
        $dateTimeObjhour = \DateTime::createFromFormat('H:i', $hour);

        if (!$dateTimeObjhour || $dateTimeObjhour->format('H:i') != $hour) {
            throw new InvalidArgumentException('Hour must be in HH:MM format and valid.');
        }

        return $hour;
    }

    public static function validateCoding(string $coding): string
    {
        $supportedCodings = [
            self::MESSAGE_CODING_DEFAULT,
            self::MESSAGE_CODING_UNICODE,
            self::MESSAGE_CODING_AUTO,
        ];

        if (!\in_array($coding, $supportedCodings, true)) {
            throw new InvalidArgumentException(sprintf('The message coding : "%s" is not supported; supported codings types are: "%s".', $coding, implode('", "', $supportedCodings)));
        }

        return $coding;
    }

    public static function validateCharset(string $charset): string
    {
        $supportedCharsets = [
            self::MESSAGE_CHARSET_ISO_1,
            self::MESSAGE_CHARSET_ISO_15,
            self::MESSAGE_CHARSET_UTF8,
        ];

        if (!\in_array($charset, $supportedCharsets, true)) {
            throw new InvalidArgumentException(sprintf('The message charset : "%s" is not supported; supported charsets types are: "%s".', $charset, implode('", "', $supportedCharsets)));
        }

        return $charset;
    }

    public static function validateUdh(int $udh): int
    {
        $supportedUdhs = [
            self::MESSAGE_UDH_6_OCTETS,
            self::MESSAGE_UDH_7_OCTETS,
            self::MESSAGE_UDH_DISABLED_CONCAT,
        ];

        if (!\in_array($udh, $supportedUdhs, true)) {
            throw new InvalidArgumentException(sprintf('The message charset : "%s" is not supported; supported charsets types are: "%s".', $udh, implode('", "', $supportedUdhs)));
        }

        return $udh;
    }

    public static function validateMaxParts(int $maxParts): int
    {
        if ($maxParts < 1 || $maxParts > 8) {
            throw new InvalidArgumentException(sprintf('The message max_parts : "%s" is not supported; supported max_parts values are integers between 1 and 8.', $maxParts));
        }

        return $maxParts;
    }

    public static function validateValidity(int $validity): int
    {
        if ($validity < 5 || $validity > 1440) {
            throw new InvalidArgumentException(sprintf('The message validity : "%s" is not supported; supported validity values are integers between 5 and 1440.', $validity));
        }

        return $validity;
    }

    public static function validateDays(int $min, int $max): array
    {
        $supportedDays = [
            self::MESSAGE_DAYS_MONDAY,
            self::MESSAGE_DAYS_TUESDAY,
            self::MESSAGE_DAYS_WEDNESDAY,
            self::MESSAGE_DAYS_THURSDAY,
            self::MESSAGE_DAYS_FRIDAY,
            self::MESSAGE_DAYS_SATURDAY,
            self::MESSAGE_DAYS_SUNDAY,
        ];

        if (!\in_array($min, $supportedDays, true)) {
            throw new InvalidArgumentException(sprintf('The message min : "%s" is not supported; supported charsets types are: "%s".', $min, implode('", "', $supportedDays)));
        }

        if (!\in_array($max, $supportedDays, true)) {
            throw new InvalidArgumentException(sprintf('The message max : "%s" is not supported; supported charsets types are: "%s".', $max, implode('", "', $supportedDays)));
        }

        if ($min > $max) {
            throw new InvalidArgumentException(sprintf('The message max must be greater than min.', $min));
        }

        return [$min, $max];
    }

    public static function validateHours(int $min, int $max): array
    {
        if ($min < 0 || $min > $max) {
            throw new InvalidArgumentException(sprintf('The message min : "%s" is not supported; supported min values are integers between 0 and 23.', $min));
        }

        if ($max > 23) {
            throw new InvalidArgumentException(sprintf('The message max : "%s" is not supported; supported min values are integers between 0 and 23.', $max));
        }

        return [$min, $max];
    }
}
