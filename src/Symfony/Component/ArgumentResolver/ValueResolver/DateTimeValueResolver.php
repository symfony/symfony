<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver;

use Psr\Clock\ClockInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Attribute\MapDateTime;
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\SourceValue;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
final readonly class DateTimeValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        if (!is_a($argument->getType(), \DateTimeInterface::class, true)) {
            return [];
        }

        $value = $value->get();

        if (SourceValue::NOT_FOUND === $value) {
            return [];
        }

        $class = \DateTimeInterface::class === $argument->getType() ? \DateTimeImmutable::class : $argument->getType();

        if (!$value) {
            if ($argument->isNullable()) {
                return [null];
            }
            if (!$this->clock) {
                return [new $class()];
            }
            $value = $this->clock->now();
        }

        if ($value instanceof \DateTimeInterface) {
            return [$value instanceof $class ? $value : $class::createFromInterface($value)];
        }

        $format = null;

        if ($attributes = $argument->getAttributes(MapDateTime::class, ArgumentMetadata::IS_INSTANCEOF)) {
            $attribute = $attributes[0];
            $format = $attribute->format;
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
            throw new InvalidSourceValueException(\sprintf('Invalid date given for parameter "%s".', $argument->getName()));
        }

        return [$date];
    }
}
