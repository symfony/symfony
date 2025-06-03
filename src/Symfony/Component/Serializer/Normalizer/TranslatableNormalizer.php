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
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatableNormalizer implements NormalizerInterface
{
    public const NORMALIZATION_LOCALE_KEY = 'translatable_normalization_locale';

    private array $defaultContext = [
        self::NORMALIZATION_LOCALE_KEY => null,
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        array $defaultContext = [],
    ) {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof TranslatableInterface) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('The object must implement the "%s".', TranslatableInterface::class), $data, [TranslatableInterface::class]);
        }

        return $data->trans($this->translator, $context[self::NORMALIZATION_LOCALE_KEY] ?? $this->defaultContext[self::NORMALIZATION_LOCALE_KEY]);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof TranslatableInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [TranslatableInterface::class => true];
    }
}
