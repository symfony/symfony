<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Normalizer;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * A helper providing autocompletion for available DateTimeNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class DateTimeNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the format of the date.
     *
     * @see https://secure.php.net/manual/en/datetime.format.php
     */
    public function withFormat(?string $format): static
    {
        return $this->with(DateTimeNormalizer::FORMAT_KEY, $format);
    }

    /**
     * Configures the timezone of the date.
     *
     * It could be either a \DateTimeZone or a string
     * that will be used to construct the \DateTimeZone
     *
     * @see https://secure.php.net/manual/en/class.datetimezone.php
     *
     * @throws InvalidArgumentException
     */
    public function withTimezone(\DateTimeZone|string|null $timezone): static
    {
        if (null === $timezone) {
            return $this->with(DateTimeNormalizer::TIMEZONE_KEY, null);
        }

        if (\is_string($timezone)) {
            try {
                $timezone = new \DateTimeZone($timezone);
            } catch (\Exception $e) {
                throw new InvalidArgumentException(sprintf('The "%s" timezone is invalid.', $timezone), previous: $e);
            }
        }

        return $this->with(DateTimeNormalizer::TIMEZONE_KEY, $timezone);
    }

    /**
     * @param 'int'|'float'|null $cast
     */
    public function withCast(?string $cast): static
    {
        return $this->with(DateTimeNormalizer::CAST_KEY, $cast);
    }
}
