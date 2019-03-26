<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Bridge for symfony/serializer.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class AutoMapperNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $autoMapper;

    public function __construct(AutoMapper $autoMapper)
    {
        $this->autoMapper = $autoMapper;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $autoMapperContext = $this->createAutoMapperContext($context);

        return $this->autoMapper->map($object, 'array', $autoMapperContext);
    }

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $autoMapperContext = $this->createAutoMapperContext($context);

        return $this->autoMapper->map($data, $class, $autoMapperContext);
    }

    public function supportsNormalization($data, $format = null)
    {
        if (!\is_object($data)) {
            return false;
        }

        return $this->autoMapper->hasMapper(\get_class($data), 'array');
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->autoMapper->hasMapper('array', $type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    private function createAutoMapperContext(array $serializerContext = []): array
    {
        $context = [
            MapperContext::GROUPS => $serializerContext[AbstractNormalizer::GROUPS] ?? null,
            MapperContext::ALLOWED_ATTRIBUTES => $serializerContext[AbstractNormalizer::ATTRIBUTES] ?? null,
            MapperContext::IGNORED_ATTRIBUTES => $serializerContext[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? null,
            MapperContext::TARGET_TO_POPULATE => $serializerContext[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null,
            MapperContext::CIRCULAR_REFERENCE_LIMIT => $serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT] ?? 1,
            MapperContext::CIRCULAR_REFERENCE_HANDLER => $serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER] ?? null,
        ];

        if ($serializerContext[AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS]) {
            foreach ($serializerContext[AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS] as $class => $keyArgs) {
                foreach ($keyArgs as $key => $value) {
                    $context[MapperContext::CONSTRUCTOR_ARGUMENTS][$class][$key] = $value;
                }
            }
        }

        return $context;
    }
}
