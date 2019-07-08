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
class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    const FORMAT_KEY = 'datetime_format';
    const TIMEZONE_KEY = 'datetime_timezone';


    /**
     * In PHP, the $timezone parameter and the current timezone are ignored when the $time parameter either is a UNIX timestamp (e.g. @946684800) or specifies a timezone (e.g. 2010-01-28T15:00:00+02:00).
     *
     * The denormalizer assumes that all DateTimeInterface object returned will have the timezone returned by the getTimezone() method.
     * Default PHP behavior will occur if the getTimezone() method returns null or context[self::PRESERVE_CONTEXT_TIMEZONE] is set to false.
     * This flag will be ignored in Symfony 5.0+
     */
    const PRESERVE_CONTEXT_TIMEZONE = 'preserve_context_timezone';

    private $defaultContext;

    private static $supportedTypes = [
        \DateTimeInterface::class => true,
        \DateTimeImmutable::class => true,
        \DateTime::class => true,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = [
            self::FORMAT_KEY => \DateTime::RFC3339,
            self::TIMEZONE_KEY => null,
        ];

        if (!\is_array($defaultContext)) {
            @trigger_error('Passing the date time format directly to the constructor is deprecated since Symfony 4.2, use the default context instead.', E_USER_DEPRECATED);

            $defaultContext = [self::FORMAT_KEY => (string) $defaultContext];
            $defaultContext[self::TIMEZONE_KEY] = $timezone;
        }

        if (!isset($defaultContext[self::TIMEZONE_KEY]) && null !== $timezone) {
            @trigger_error('Passing the time zone directly to the constructor is deprecated since Symfony 4.2, use the default context instead.', E_USER_DEPRECATED);

            $defaultContext[self::TIMEZONE_KEY] = $timezone;
        }

        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, string $format = null, array $context = [])
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

        return $object->format($dateTimeFormat);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof \DateTimeInterface;
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, $class, string $format = null, array $context = [])
    {
        $dateTimeFormat = $context[self::FORMAT_KEY] ?? null;
        $timezone = $this->getTimezone($context);
        $preserveContextTimezone = $this->isPreserveContextTimezone($context);

        if ('' === $data || null === $data) {
            throw new NotNormalizableValueException('The data is either an empty string or null, you should pass a string that can be parsed with the passed format or a valid DateTime string.');
        }

        if (null !== $dateTimeFormat) {
            $object = \DateTime::createFromFormat($dateTimeFormat, $data, $timezone);

            if (false !== $object) {
                if ($preserveContextTimezone) {
                    $object->setTimezone($timezone);
                }

                return \DateTime::class === $class ? $object : new \DateTimeImmutable($object->format(\DATE_RFC3339));
            }

            $dateTimeErrors = \DateTime::class === $class ? \DateTime::getLastErrors() : \DateTimeImmutable::getLastErrors();

            throw new NotNormalizableValueException(sprintf(
                'Parsing datetime string "%s" using format "%s" resulted in %d errors:'."\n".'%s',
                $data,
                $dateTimeFormat,
                $dateTimeErrors['error_count'],
                implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors']))
            ));
        }

        try {
            $object = new \DateTime($data, $timezone);

            if ($preserveContextTimezone) {
                $object->setTimezone($timezone);
            }

            return \DateTime::class === $class ? $object : new \DateTimeImmutable($object->format(\DATE_RFC3339));
        } catch (\Exception $e) {
            throw new NotNormalizableValueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, string $format = null)
    {
        return isset(self::$supportedTypes[$type]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === \get_class($this);
    }

    /**
     * Formats datetime errors.
     *
     * @return string[]
     */
    private function formatDateTimeErrors(array $errors)
    {
        $formattedErrors = [];

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }

    private function getTimezone(array $context)
    {
        $dateTimeZone = $context[self::TIMEZONE_KEY] ?? $this->defaultContext[self::TIMEZONE_KEY];

        if (null === $dateTimeZone) {
            return null;
        }

        return $dateTimeZone instanceof \DateTimeZone ? $dateTimeZone : new \DateTimeZone($dateTimeZone);
    }

    private function isPreserveContextTimezone(array $context): bool
    {
        // Version 5.0 of Symfony/Serializer will always preserve the context timezone, so this method always will return true, unless the timezone equals null.
        if (null === $this->getTimezone($context)) {
            return false;
        }

        if (!isset($context[self::PRESERVE_CONTEXT_TIMEZONE]) && !isset($this->defaultContext[self::PRESERVE_CONTEXT_TIMEZONE])) {
            @trigger_error('Not setting the boolean "PRESERVE_CONTEXT_TIMEZONE" flag is deprecated. Set the flag to "true" to apply the context timezone consistently, otherwise setting the flag to "false" will preserve default PHP behavior.', E_USER_DEPRECATED);
        }

        if (!isset($context[self::PRESERVE_CONTEXT_TIMEZONE])) {
            return (bool) isset($this->defaultContext[self::PRESERVE_CONTEXT_TIMEZONE]) ? $this->defaultContext[self::PRESERVE_CONTEXT_TIMEZONE] : false;
        }

        return (bool) isset($context[self::PRESERVE_CONTEXT_TIMEZONE]) ? $context[self::PRESERVE_CONTEXT_TIMEZONE] : false;
    }
}
