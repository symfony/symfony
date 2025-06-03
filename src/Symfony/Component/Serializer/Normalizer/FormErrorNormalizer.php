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

/**
 * Normalizes invalid Form instances.
 */
final class FormErrorNormalizer implements NormalizerInterface
{
    public const TITLE = 'title';
    public const TYPE = 'type';
    public const CODE = 'status_code';

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $error = [
            'title' => $context[self::TITLE] ?? 'Validation Failed',
            'type' => $context[self::TYPE] ?? 'https://symfony.com/errors/form',
            'code' => $context[self::CODE] ?? null,
            'errors' => $this->convertFormErrorsToArray($data),
        ];

        if (0 !== \count($data->all())) {
            $error['children'] = $this->convertFormChildrenToArray($data);
        }

        return $error;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            FormInterface::class => false,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
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

            if ($child->all()) {
                $childData['children'] = $this->convertFormChildrenToArray($child);
            }

            $children[$child->getName()] = $childData;
        }

        return $children;
    }
}
