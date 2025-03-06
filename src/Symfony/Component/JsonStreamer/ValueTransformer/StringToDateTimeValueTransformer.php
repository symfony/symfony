<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\ValueTransformer;

use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

/**
 * Transforms string to DateTimeImmutable during stream reading.
 *
 * Does nothing if the stream value type is not valid.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class StringToDateTimeValueTransformer implements ValueTransformerInterface
{
    public const FORMAT_KEY = 'date_time_format';

    public function transform(mixed $value, array $options = []): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }

        $dateTimeFormat = $options[self::FORMAT_KEY] ?? null;

        if (null !== $dateTimeFormat) {
            if (false !== $dateTime = \DateTimeImmutable::createFromFormat($dateTimeFormat, $value)) {
                return $dateTime;
            }

            $dateTimeErrors = \DateTimeImmutable::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" using format "%s" resulted in %d errors: ', $value, $dateTimeFormat, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            $dateTimeErrors = \DateTimeImmutable::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" resulted in %d errors: ', $value, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
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
