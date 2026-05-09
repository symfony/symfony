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
 * Transforms a {@see \DateInterval} to a string and vice versa.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @psalm-type Options = array{
 *     date_interval_format?: string,
 *     ...<string, mixed>,
 * }
 *
 * @implements ValueObjectTransformerInterface<\DateInterval, string>
 */
final class DateIntervalValueObjectTransformer implements ValueObjectTransformerInterface
{
    public const FORMAT_KEY = 'date_interval_format';

    private const DEFAULT_FORMAT = '%rP%yY%mM%dDT%hH%iM%sS';

    /**
     * @param Options $options
     */
    public function transform(object $object, array $options = []): string
    {
        if (!$object instanceof \DateInterval) {
            throw new InvalidArgumentException('The native value must be an instance of "\DateInterval".');
        }

        return $object->format($options[self::FORMAT_KEY] ?? self::DEFAULT_FORMAT);
    }

    /**
     * @param Options $options
     */
    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): \DateInterval
    {
        if (!\is_string($scalar) || !$this->isISO8601($scalar)) {
            throw new InvalidArgumentException('The JSON value is not a valid ISO 8601 interval string.');
        }

        $dateIntervalFormat = $options[self::FORMAT_KEY] ?? self::DEFAULT_FORMAT;
        $signPattern = match (substr($dateIntervalFormat, 0, 2)) {
            '%R' => '[-+]',
            '%r' => '-?',
            default => '',
        };

        if ('' !== $signPattern) {
            $dateIntervalFormat = substr($dateIntervalFormat, 2);
        }

        $valuePattern = '/^'.$signPattern.preg_replace('/%([yYmMdDhHiIsSwW])(\w)/', '(?:(?P<$1>\d+)$2)?', preg_replace('/(T.*)$/', '($1)?', $dateIntervalFormat)).'$/';
        if (!preg_match($valuePattern, $scalar)) {
            throw new InvalidArgumentException(\sprintf('The JSON value "%s" contains intervals not accepted by format "%s".', $scalar, $options[self::FORMAT_KEY] ?? self::DEFAULT_FORMAT));
        }

        try {
            $interval = new \DateInterval(ltrim($scalar, '+-'));
            if ('-' === $scalar[0]) {
                $interval->invert = 1;
            }

            return $interval;
        } catch (\Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
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
        return \DateInterval::class;
    }

    private function isISO8601(string $string): bool
    {
        return (bool) preg_match('/^[\-+]?P(?=\w*(?:\d|%\w))(?:\d+Y|%[yY]Y)?(?:\d+M|%[mM]M)?(?:\d+W|%[wW]W)?(?:\d+D|%[dD]D)?(?:T(?:\d+H|[hH]H)?(?:\d+M|[iI]M)?(?:\d+S|[sS]S)?)?$/', $string);
    }
}
