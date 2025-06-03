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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Transforms string to DateTimeImmutable during stream reading.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class StringToDateTimeValueTransformer implements ValueTransformerInterface
{
    public const FORMAT_KEY = 'date_time_format';

    public function transform(mixed $value, array $options = []): \DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            throw new InvalidArgumentException('The JSON value is either not an string, or an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.');
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

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getStreamValueType(): BuiltinType
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
