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
    const INSTANCE = 'instance';
    const STATUS = 'status';
    const TITLE = 'title';
    const TYPE = 'type';

    private $defaultContext;
    private $nameConverter;

    public function __construct($defaultContext = [], NameConverterInterface $nameConverter = null)
    {
        $this->defaultContext = $defaultContext;
        $this->nameConverter = $nameConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
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
    public function supportsNormalization($data, $format = null)
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
