<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes an object implementing the {@see \DateTimeInterface} to a date string.
 * Denormalizes a date string to an instance of {@see \DateTime} or {@see \DateTimeImmutable}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const FORMAT_KEY = 'datetime_format';
    public const TIMEZONE_KEY = 'datetime_timezone';
    public const CAST_KEY = 'datetime_cast';

    private array $defaultContext = [
        self::FORMAT_KEY => \DateTimeInterface::RFC3339,
        self::TIMEZONE_KEY => null,
        self::CAST_KEY => null,
    ];

    private const SUPPORTED_TYPES = [
        \DateTimeInterface::class => true,
        \DateTimeImmutable::class => true,
        \DateTime::class => true,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->setDefaultContext($defaultContext);
    }

    public function setDefaultContext(array $defaultContext): void
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function getSupportedTypes(?string $format): array
    {
        return self::SUPPORTED_TYPES;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): int|float|string
    {
        if (!$object instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('The object must implement the "\DateTimeInterface".');
        }

        $dateTimeFormat = $context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY];
        $timezone = $this->getTimezone($context);

        if (null !== $timezone) {
            $object = clone $object;
            $object = $object->setTimezone($timezone);
        }

        return match ($context[self::CAST_KEY] ?? $this->defaultContext[self::CAST_KEY] ?? false) {
            'int' => (int) $object->format($dateTimeFormat),
            'float' => (float) $object->format($dateTimeFormat),
            default => $object->format($dateTimeFormat),
        };
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \DateTimeInterface;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeInterface
    {
        if (\is_int($data) || \is_float($data)) {
            switch ($context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY] ?? null) {
                case 'U':
                    $data = \sprintf('%d', $data);
                    break;
                case 'U.u':
                    $data = \sprintf('%.6F', $data);
                    break;
            }
        }

        if (!\is_string($data) || '' === trim($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.', $data, ['string'], $context['deserialization_path'] ?? null, true);
        }

        try {
            if (\DateTimeInterface::class === $type) {
                $type = \DateTimeImmutable::class;
            }

            $timezone = $this->getTimezone($context);
            $dateTimeFormat = $context[self::FORMAT_KEY] ?? null;

            if (null !== $dateTimeFormat) {
                if (false !== $object = $type::createFromFormat($dateTimeFormat, $data, $timezone)) {
                    return $object;
                }

                $dateTimeErrors = $type::getLastErrors();

                throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Parsing datetime string "%s" using format "%s" resulted in %d errors: ', $data, $dateTimeFormat, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])), $data, ['string'], $context['deserialization_path'] ?? null, true);
            }

            $defaultDateTimeFormat = $this->defaultContext[self::FORMAT_KEY] ?? null;

            if (null !== $defaultDateTimeFormat) {
                if (false !== $object = $type::createFromFormat($defaultDateTimeFormat, $data, $timezone)) {
                    return $object;
                }
            }

            return new $type($data, $timezone);
        } catch (NotNormalizableValueException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['string'], $context['deserialization_path'] ?? null, false, $e->getCode(), $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, \DateTimeInterface::class, true);
    }

    /**
     * Formats datetime errors.
     *
     * @return string[]
     */
    private function formatDateTimeErrors(array $errors): array
    {
        $formattedErrors = [];

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = \sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }

    private function getTimezone(array $context): ?\DateTimeZone
    {
        $dateTimeZone = $context[self::TIMEZONE_KEY] ?? $this->defaultContext[self::TIMEZONE_KEY];

        if (null === $dateTimeZone) {
            return null;
        }

        return $dateTimeZone instanceof \DateTimeZone ? $dateTimeZone : new \DateTimeZone($dateTimeZone);
    }
}
