<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver\ValueResolver;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\MapDateTime;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves a \DateTime* instance as a command input argument or option.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class DateTimeValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $type = $member->getType();

        if (!$type instanceof \ReflectionNamedType || !is_a($type->getName(), \DateTimeInterface::class, true)) {
            return [];
        }

        $attribute = $member->getAttribute(MapDateTime::class);

        $inputName = $attribute?->argument ?? $attribute?->option ?? $member->getInputName();

        // Try to get value from argument or option
        $value = null;
        if ($input->hasArgument($inputName)) {
            $value = $input->getArgument($inputName);
        } elseif ($input->hasOption($inputName)) {
            $value = $input->getOption($inputName);
        }

        /** @var class-string<\DateTimeImmutable>|class-string<\DateTime> $class */
        $class = \DateTimeInterface::class === $type->getName() ? \DateTimeImmutable::class : $type->getName();

        if ($member->isVariadic()) {
            return array_map(fn ($value) => $this->resolveDate($value, $class, $attribute?->format, $argumentName), (array) $value);
        }

        if (!$value) {
            if ($member->isNullable()) {
                return [null];
            }
            if (!$this->clock) {
                return [new $class()];
            }
            $value = $this->clock->now();
        }

        return [$this->resolveDate($value, $class, $attribute?->format, $argumentName)];
    }

    /**
     * @param class-string<\DateTimeImmutable>|class-string<\DateTime> $class
     */
    private function resolveDate(mixed $value, string $class, ?string $format, string $argumentName): \DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value instanceof $class ? $value : $class::createFromInterface($value);
        }

        if (null !== $format) {
            $date = $class::createFromFormat($format, $value, $this->clock?->now()->getTimeZone());

            if (($class::getLastErrors() ?: ['warning_count' => 0])['warning_count']) {
                $date = false;
            }
        } else {
            if (false !== filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
                $value = '@'.$value;
            }
            try {
                $date = new $class($value, $this->clock?->now()->getTimeZone());
            } catch (\Exception) {
                $date = false;
            }
        }

        if (!$date) {
            $message = \sprintf('Invalid date given for parameter "$%s".', $argumentName);
            if ($format) {
                $message .= \sprintf(' Expected format: "%s".', $format);
            }
            $message .= ' Use #[MapDateTime(format: \'your-format\')] to specify a custom format.';

            throw new \RuntimeException($message);
        }

        return $this->clock ? $this->fillFromClock($class, $date, $value, $format) : $date;
    }

    /**
     * Takes the fields that $value leaves out from the clock instead of from the system time.
     *
     * @param class-string<\DateTimeImmutable>|class-string<\DateTime> $class
     */
    private function fillFromClock(string $class, \DateTimeInterface $date, string $value, ?string $format): \DateTimeInterface
    {
        $now = $class::createFromInterface($this->clock->now())->setTimezone($date->getTimezone());

        if (null !== $format) {
            // createFromFormat() takes the fields that $format does not parse from the system time, so we prepend them from the clock
            $parsed = date_parse_from_format($format, $value);
            $nowFormat = $nowValue = '';

            foreach (['year' => 'Y', 'month' => 'm', 'day' => 'd', 'hour' => 'H', 'minute' => 'i', 'second' => 's'] as $field => $char) {
                if (false === $parsed[$field]) {
                    $nowFormat .= $char.'-';
                    $nowValue .= $now->format($char).'-';
                }
            }

            return '' === $nowFormat ? $date : $class::createFromFormat($nowFormat.$format, $nowValue.$value, $date->getTimezone());
        }

        $parsed = date_parse($value);

        // $date does not depend on the system time when $value sets the full date
        if (false !== $parsed['year'] && false !== $parsed['month'] && false !== $parsed['day']) {
            return $date;
        }

        // The constructor starts from midnight when $value has a date but no time, and resets the microseconds when $value sets any field. modify() does neither.
        if (false === $parsed['hour'] && (false !== $parsed['month'] || false !== $parsed['day'])) {
            $value = 'midnight '.$value;
        } elseif (false === $parsed['fraction'] && (false !== $parsed['year'] || false !== $parsed['hour'])) {
            $now = $now->setTimestamp($now->getTimestamp());
        }

        return $now->modify($value);
    }
}
