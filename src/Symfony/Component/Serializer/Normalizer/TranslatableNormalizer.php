<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const LOCALE_KEY = 'locale';

    private $defaultContext = [
        self::LOCALE_KEY => null,
    ];

    private $translator;

    public function __construct(array $defaultContext = [], TranslatorInterface $translator = null)
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
        $this->translator = $translator;
    }

    public function normalize($object, $format = null, array $context = []): string
    {
        return $object->trans($this->translator, $context[self::LOCALE_KEY] ?? $this->defaultContext[self::LOCALE_KEY]);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof TranslatableInterface &&
            $this->translator instanceof TranslatorInterface;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
