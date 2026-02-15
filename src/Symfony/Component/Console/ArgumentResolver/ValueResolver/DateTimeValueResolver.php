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

        $class = \DateTimeInterface::class === $type->getName() ? \DateTimeImmutable::class : $type->getName();

        if (!$value) {
            if ($member->isNullable()) {
                return [null];
            }
            if (!$this->clock) {
                return [new $class()];
            }
            $value = $this->clock->now();
        }

        if ($value instanceof \DateTimeInterface) {
            /** @var class-string<\DateTimeImmutable>|class-string<\DateTime> $class */
            return [$value instanceof $class ? $value : $class::createFromInterface($value)];
        }

        $format = $attribute?->format;

        /** @var class-string<\DateTimeImmutable>|class-string<\DateTime> $class */
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

        return [$date];
    }
}
