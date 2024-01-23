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
 * Normalizes an instance of {@see \DateInterval} to an interval string.
 * Denormalizes an interval string to an instance of {@see \DateInterval}.
 *
 * @author Jérôme Parmentier <jerome@prmntr.me>
 */
class DateIntervalNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public const FORMAT_KEY = 'dateinterval_format';

    private $defaultContext = [
        self::FORMAT_KEY => '%rP%yY%mM%dDT%hH%iM%sS',
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        if (!$object instanceof \DateInterval) {
            throw new InvalidArgumentException('The object must be an instance of "\DateInterval".');
        }

        return $object->format($context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, ?string $format = null)
    {
        return $data instanceof \DateInterval;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * {@inheritdoc}
     *
     * @return \DateInterval
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
    {
        if (!\is_string($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('Data expected to be a string.', $data, ['string'], $context['deserialization_path'] ?? null, true);
        }

        if (!$this->isISO8601($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('Expected a valid ISO 8601 interval string.', $data, ['string'], $context['deserialization_path'] ?? null, true);
        }

        $dateIntervalFormat = $context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY];

        $signPattern = '';
        switch (substr($dateIntervalFormat, 0, 2)) {
            case '%R':
                $signPattern = '[-+]';
                $dateIntervalFormat = substr($dateIntervalFormat, 2);
                break;
            case '%r':
                $signPattern = '-?';
                $dateIntervalFormat = substr($dateIntervalFormat, 2);
                break;
        }
        $valuePattern = '/^'.$signPattern.preg_replace('/%([yYmMdDhHiIsSwW])(\w)/', '(?:(?P<$1>\d+)$2)?', preg_replace('/(T.*)$/', '($1)?', $dateIntervalFormat)).'$/';
        if (!preg_match($valuePattern, $data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Value "%s" contains intervals not accepted by format "%s".', $data, $dateIntervalFormat), $data, ['string'], $context['deserialization_path'] ?? null, false);
        }

        try {
            if ('-' === $data[0]) {
                $interval = new \DateInterval(substr($data, 1));
                $interval->invert = 1;

                return $interval;
            }

            if ('+' === $data[0]) {
                return new \DateInterval(substr($data, 1));
            }

            return new \DateInterval($data);
        } catch (\Exception $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['string'], $context['deserialization_path'] ?? null, false, $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, ?string $format = null)
    {
        return \DateInterval::class === $type;
    }

    private function isISO8601(string $string): bool
    {
        if (\PHP_VERSION_ID >= 80000) {
            return preg_match('/^[\-+]?P(?=\w*(?:\d|%\w))(?:\d+Y|%[yY]Y)?(?:\d+M|%[mM]M)?(?:\d+W|%[wW]W)?(?:\d+D|%[dD]D)?(?:T(?:\d+H|[hH]H)?(?:\d+M|[iI]M)?(?:\d+S|[sS]S)?)?$/', $string);
        }

        return preg_match('/^[\-+]?P(?=\w*(?:\d|%\w))(?:\d+Y|%[yY]Y)?(?:\d+M|%[mM]M)?(?:(?:\d+D|%[dD]D)|(?:\d+W|%[wW]W))?(?:T(?:\d+H|[hH]H)?(?:\d+M|[iI]M)?(?:\d+S|[sS]S)?)?$/', $string);
    }
}
