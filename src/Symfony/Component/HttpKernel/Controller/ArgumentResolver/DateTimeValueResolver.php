<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
final class DateTimeValueResolver implements ArgumentValueResolverInterface, ValueResolverInterface
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @deprecated since Symfony 6.2, use resolve() instead
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        @trigger_deprecation('symfony/http-kernel', '6.2', 'The "%s()" method is deprecated, use "resolve()" instead.', __METHOD__);

        return is_a($argument->getType(), \DateTimeInterface::class, true) && $request->attributes->has($argument->getName());
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!is_a($argument->getType(), \DateTimeInterface::class, true) || !$request->attributes->has($argument->getName())) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());
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
            throw new NotFoundHttpException(\sprintf('Invalid date given for parameter "%s".', $argument->getName()));
        }

        return [$this->clock ? $this->fillFromClock($class, $date, $value, $format) : $date];
    }

    /**
     * Takes the fields that $value leaves out from the clock instead of from the system time.
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
