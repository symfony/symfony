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
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Normalizes an object implementing the {@see \DateTimeInterface} to a date string.
 * Denormalizes a date string to an instance of {@see \DateTime} or {@see \DateTimeImmutable}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    const FORMAT_KEY = 'datetime_format';

    private $format;

    /**
     * @param string $format
     */
    public function __construct($format = \DateTime::RFC3339)
    {
        $this->format = $format;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if (!$object instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('The object must implement the "\DateTimeInterface".');
        }

        $format = isset($context[self::FORMAT_KEY]) ? $context[self::FORMAT_KEY] : $this->format;

        return $object->format($format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \DateTimeInterface;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $dateTimeFormat = isset($context[self::FORMAT_KEY]) ? $context[self::FORMAT_KEY] : null;

        if (null !== $dateTimeFormat) {
            $object = \DateTime::class === $class ? \DateTime::createFromFormat($dateTimeFormat, $data) : \DateTimeImmutable::createFromFormat($dateTimeFormat, $data);

            if (false !== $object) {
                return $object;
            }

            $dateTimeErrors = \DateTime::class === $class ? \DateTime::getLastErrors() : \DateTimeImmutable::getLastErrors();

            throw new UnexpectedValueException(sprintf(
                'Parsing datetime string "%s" using format "%s" resulted in %d errors:'."\n".'%s',
                $data,
                $dateTimeFormat,
                $dateTimeErrors['error_count'],
                implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors']))
            ));
        }

        try {
            return \DateTime::class === $class ? new \DateTime($data) : new \DateTimeImmutable($data);
        } catch (\Exception $e) {
            throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        $supportedTypes = array(
            \DateTimeInterface::class => true,
            \DateTimeImmutable::class => true,
            \DateTime::class => true,
        );

        return isset($supportedTypes[$type]);
    }

    /**
     * Formats datetime errors.
     *
     * @return string[]
     */
    private function formatDateTimeErrors(array $errors)
    {
        $formattedErrors = array();

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }
}
