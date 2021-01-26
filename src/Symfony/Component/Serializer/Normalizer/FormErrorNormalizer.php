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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Serializer\Result\NormalizationResult;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Normalizes invalid Form instances.
 */
final class FormErrorNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const TITLE = 'title';
    public const TYPE = 'type';
    public const CODE = 'status_code';

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $data = [
            'title' => $context[self::TITLE] ?? 'Validation Failed',
            'type' => $context[self::TYPE] ?? 'https://symfony.com/errors/form',
            'code' => $context[self::CODE] ?? null,
            'errors' => $this->convertFormErrorsToArray($object),
        ];

        if (0 !== \count($object->all())) {
            $data['children'] = $this->convertFormChildrenToArray($object);
        }

        if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
            return NormalizationResult::success($data);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof FormInterface && $data->isSubmitted() && !$data->isValid();
    }

    private function convertFormErrorsToArray(FormInterface $data): array
    {
        $errors = [];

        foreach ($data->getErrors() as $error) {
            $errors[] = [
                'message' => $error->getMessage(),
                'cause' => $error->getCause(),
            ];
        }

        return $errors;
    }

    private function convertFormChildrenToArray(FormInterface $data): array
    {
        $children = [];

        foreach ($data->all() as $child) {
            $childData = [
                'errors' => $this->convertFormErrorsToArray($child),
            ];

            if (!empty($child->all())) {
                $childData['children'] = $this->convertFormChildrenToArray($child);
            }

            $children[$child->getName()] = $childData;
        }

        return $children;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
