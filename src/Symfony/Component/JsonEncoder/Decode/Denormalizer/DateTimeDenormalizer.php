<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode\Denormalizer;

use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Casts string to DateTimeInterface.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class DateTimeDenormalizer implements DenormalizerInterface
{
    public const FORMAT_KEY = 'date_time_format';

    public function __construct(
        private bool $immutable,
    ) {
    }

    public function denormalize(mixed $normalized, array $options = []): \DateTime|\DateTimeImmutable
    {
        if (!\is_string($normalized) || '' === trim($normalized)) {
            throw new InvalidArgumentException('The normalized data is either not an string, or an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.');
        }

        $dateTimeFormat = $options[self::FORMAT_KEY] ?? null;
        $dateTimeClassName = $this->immutable ? \DateTimeImmutable::class : \DateTime::class;

        if (null !== $dateTimeFormat) {
            if (false !== $dateTime = $dateTimeClassName::createFromFormat($dateTimeFormat, $normalized)) {
                return $dateTime;
            }

            $dateTimeErrors = $dateTimeClassName::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" using format "%s" resulted in %d errors: ', $normalized, $dateTimeFormat, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }

        try {
            return new $dateTimeClassName($normalized);
        } catch (\Throwable) {
            $dateTimeErrors = $dateTimeClassName::getLastErrors();

            throw new InvalidArgumentException(\sprintf('Parsing datetime string "%s" resulted in %d errors: ', $normalized, $dateTimeErrors['error_count'])."\n".implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])));
        }
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getNormalizedType(): BuiltinType
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
