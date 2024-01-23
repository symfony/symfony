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

use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * A normalizer that normalizes a ConstraintViolationListInterface instance.
 *
 * This Normalizer implements RFC7807 {@link https://tools.ietf.org/html/rfc7807}.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationListNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const INSTANCE = 'instance';
    public const STATUS = 'status';
    public const TITLE = 'title';
    public const TYPE = 'type';
    public const PAYLOAD_FIELDS = 'payload_fields';

    private $defaultContext;
    private $nameConverter;

    public function __construct(array $defaultContext = [], ?NameConverterInterface $nameConverter = null)
    {
        $this->defaultContext = $defaultContext;
        $this->nameConverter = $nameConverter;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        if (\array_key_exists(self::PAYLOAD_FIELDS, $context)) {
            $payloadFieldsToSerialize = $context[self::PAYLOAD_FIELDS];
        } elseif (\array_key_exists(self::PAYLOAD_FIELDS, $this->defaultContext)) {
            $payloadFieldsToSerialize = $this->defaultContext[self::PAYLOAD_FIELDS];
        } else {
            $payloadFieldsToSerialize = [];
        }

        if (\is_array($payloadFieldsToSerialize) && [] !== $payloadFieldsToSerialize) {
            $payloadFieldsToSerialize = array_flip($payloadFieldsToSerialize);
        }

        $violations = [];
        $messages = [];
        foreach ($object as $violation) {
            $propertyPath = $this->nameConverter ? $this->nameConverter->normalize($violation->getPropertyPath(), null, $format, $context) : $violation->getPropertyPath();

            $violationEntry = [
                'propertyPath' => $propertyPath,
                'title' => $violation->getMessage(),
                'parameters' => $violation->getParameters(),
            ];
            if (null !== $code = $violation->getCode()) {
                $violationEntry['type'] = sprintf('urn:uuid:%s', $code);
            }

            $constraint = $violation->getConstraint();
            if (
                [] !== $payloadFieldsToSerialize &&
                $constraint &&
                $constraint->payload &&
                // If some or all payload fields are whitelisted, add them
                $payloadFields = null === $payloadFieldsToSerialize || true === $payloadFieldsToSerialize ? $constraint->payload : array_intersect_key($constraint->payload, $payloadFieldsToSerialize)
            ) {
                $violationEntry['payload'] = $payloadFields;
            }

            $violations[] = $violationEntry;

            $prefix = $propertyPath ? sprintf('%s: ', $propertyPath) : '';
            $messages[] = $prefix.$violation->getMessage();
        }

        $result = [
            'type' => $context[self::TYPE] ?? $this->defaultContext[self::TYPE] ?? 'https://symfony.com/errors/validation',
            'title' => $context[self::TITLE] ?? $this->defaultContext[self::TITLE] ?? 'Validation Failed',
        ];
        if (null !== $status = ($context[self::STATUS] ?? $this->defaultContext[self::STATUS] ?? null)) {
            $result['status'] = $status;
        }
        if ($messages) {
            $result['detail'] = implode("\n", $messages);
        }
        if (null !== $instance = ($context[self::INSTANCE] ?? $this->defaultContext[self::INSTANCE] ?? null)) {
            $result['instance'] = $instance;
        }

        return $result + ['violations' => $violations];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, ?string $format = null)
    {
        return $data instanceof ConstraintViolationListInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
