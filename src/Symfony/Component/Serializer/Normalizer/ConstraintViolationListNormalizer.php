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

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * A normalizer that normalizes a ConstraintViolationListInterface instance.
 *
 * This Normalizer implements RFC7807 {@link https://tools.ietf.org/html/rfc7807}.
 *
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationListNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $violations = array();
        $messages = array();
        foreach ($object as $violation) {
            $propertyPath = $violation->getPropertyPath();

            $violationEntry = array(
                'propertyPath' => $propertyPath,
                'title' => $violation->getMessage(),
            );
            if (null !== $code = $violation->getCode()) {
                $violationEntry['type'] = sprintf('urn:uuid:%s', $code);
            }

            $violations[] = $violationEntry;

            $prefix = $propertyPath ? sprintf('%s: ', $propertyPath) : '';
            $messages[] = $prefix.$violation->getMessage();
        }

        $result = array(
            'type' => $context['type'] ?? 'https://symfony.com/errors/validation',
            'title' => $context['title'] ?? 'Validation Failed',
        );
        if (isset($context['status'])) {
            $result['status'] = $context['status'];
        }
        if ($messages) {
            $result['detail'] = implode("\n", $messages);
        }
        if (isset($context['instance'])) {
            $result['instance'] = $context['instance'];
        }

        return $result + array('violations' => $violations);
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
        return __CLASS__ === \get_class($this);
    }
}
