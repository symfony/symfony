<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Transformer;

use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Transforms a {@see \DateTimeInterface} to a string and vice versa.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @psalm-type Options = array{
 *     date_time_format?: string,
 *     date_time_timezone?: string|\DateTimeZone,
 *     ...<string, mixed>,
 * }
 *
 * @implements ValueObjectTransformerInterface<\DateTimeInterface, string>
 */
final class DateTimeValueObjectTransformer implements ValueObjectTransformerInterface
{
    public const FORMAT_KEY = 'date_time_format';
    public const TIMEZONE_KEY = 'date_time_timezone';

    /**
     * @param Options $options
     */
    public function transform(object $object, array $options = []): string
    {
        if (!$object instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('The native value must implement the "\DateTimeInterface".');
        }

        $timezone = $this->getTimezone($options);
        if ($timezone && method_exists($object, 'setTimezone')) {
            $object = (clone $object)->setTimezone($timezone);
        }

        return $object->format($options[self::FORMAT_KEY] ?? \DateTimeInterface::RFC3339);
    }

    /**
     * @param Options $options
     */
    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): \DateTimeImmutable
    {
        if (!\is_string($scalar) || '' === trim($scalar)) {
            throw new InvalidArgumentException('The JSON value is either not an string, or an empty string; you should pass a string that can be parsed with the passed format or a valid DateTime string.');
        }

        $dateTimeFormat = $options[self::FORMAT_KEY] ?? null;
        $timezone = $this->getTimezone($options);

        if (null !== $dateTimeFormat) {
            if ($dateTime = \DateTimeImmutable::createFromFormat($dateTimeFormat, $scalar, $timezone)) {
                return $dateTime;
            }

            $dateTimeErrors = \DateTimeImmutable::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" using format "%s" resulted in %d errors: ', $scalar, $dateTimeFormat, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }

        try {
            return new \DateTimeImmutable($scalar, $timezone);
        } catch (\Throwable) {
            $dateTimeErrors = \DateTimeImmutable::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" resulted in %d errors: ', $scalar, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();
    }

    public static function getValueObjectClassName(): string
    {
        return \DateTimeInterface::class;
    }

    /**
     * @param Options $options
     */
    private function getTimezone(array $options): ?\DateTimeZone
    {
        if (!($dateTimeZone = $options[self::TIMEZONE_KEY] ?? null)) {
            return null;
        }

        return $dateTimeZone instanceof \DateTimeZone ? $dateTimeZone : new \DateTimeZone($dateTimeZone);
    }

    /**
     * @param array<int, string> $errors
     *
     * @return list<string>
     */
    private function formatDateTimeErrors(array $errors): array
    {
        $formattedErrors = [];

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = \sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }
}
