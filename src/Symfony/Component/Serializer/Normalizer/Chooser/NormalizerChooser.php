<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer\Chooser;

use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NormalizerChooser implements NormalizerChooserInterface
{
    private $normalizerCache = [];
    private $denormalizerCache = [];
    private $normalizer;
    private $denormalizer;
    private $serializer;

    public function __construct(NormalizerInterface $normalizer, DenormalizerInterface $denormalizer, SerializerInterface $serializer)
    {
        $this->normalizer = $normalizer;
        $this->denormalizer = $denormalizer;
        $this->serializer = $serializer;
    }

    public function chooseNormalizer(array $normalizers, $data, ?string $format = null, array $context = []): ?NormalizerInterface
    {
        $type = \is_object($data) ? \get_class($data) : 'native-'.\gettype($data);

        if (!isset($this->normalizerCache[$format][$type])) {
            $this->normalizerCache[$format][$type] = [];

            foreach ($normalizers as $key => $normalizer) {
                if (!$normalizer instanceof NormalizerInterface) {
                    continue;
                }

                $normalizer = $this->prepareNormalizer($normalizer);

                if (!$normalizer instanceof CacheableSupportsMethodInterface || !$normalizer->hasCacheableSupportsMethod()) {
                    $this->normalizerCache[$format][$type][$key] = false;
                } elseif ($normalizer->supportsNormalization($data, $format, $context)) {
                    $this->normalizerCache[$format][$type][$key] = true;
                    break;
                }
            }
        }

        foreach ($this->normalizerCache[$format][$type] as $key => $cached) {
            $normalizer = $normalizers[$key];
            if ($cached || $normalizer->supportsNormalization($data, $format, $context)) {
                return $normalizer;
            }
        }

        return null;
    }

    public function chooseDenormalizer(array $denormalizers, $data, string $class, ?string $format = null, array $context = []): ?DenormalizerInterface
    {
        if (!isset($this->denormalizerCache[$format][$class])) {
            $this->denormalizerCache[$format][$class] = [];

            foreach ($denormalizers as $key => $denormalizer) {
                if (!$denormalizer instanceof DenormalizerInterface) {
                    continue;
                }

                $denormalizer = $this->prepareNormalizer($denormalizer);

                if (!$denormalizer instanceof CacheableSupportsMethodInterface || !$denormalizer->hasCacheableSupportsMethod()) {
                    $this->denormalizerCache[$format][$class][$key] = false;
                } elseif ($denormalizer->supportsDenormalization(null, $class, $format, $context)) {
                    $this->denormalizerCache[$format][$class][$key] = true;
                    break;
                }
            }
        }

        foreach ($this->denormalizerCache[$format][$class] as $key => $cached) {
            $denormalizer = $denormalizers[$key];
            if ($cached || $denormalizer->supportsDenormalization($data, $class, $format, $context)) {
                return $denormalizer;
            }
        }

        return null;
    }

    private function prepareNormalizer($normalizer)
    {
        if ($normalizer instanceof NormalizerAwareInterface) {
            $normalizer->setNormalizer($this->normalizer);
        }

        if ($normalizer instanceof DenormalizerAwareInterface) {
            $normalizer->setDenormalizer($this->denormalizer);
        }

        if ($normalizer instanceof SerializerAwareInterface) {
            $normalizer->setSerializer($this->serializer);
        }

        return $normalizer;
    }
}
