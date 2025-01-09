<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver\Traits;

use Psr\Clock\ClockInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidRawValueException;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
trait DateTimeValueResolverTrait
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    private function doResolve(ArgumentMetadata $argument, array $rawValues): iterable
    {
        if (!is_a($argument->getType(), \DateTimeInterface::class, true)) {
            return [];
        }

        $value = $rawValues[0] ?? null;
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
            throw new InvalidRawValueException(\sprintf('Invalid date given for parameter "%s".', $argument->getName()));
        }

        return [$date];
    }
}
