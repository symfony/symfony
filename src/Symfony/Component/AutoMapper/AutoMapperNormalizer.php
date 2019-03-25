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
 * @expiremental
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

    private function createAutoMapperContext(array $serializerContext = []): Context
    {
        $circularReferenceLimit = 1;

        if (isset($serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT]) && \is_int($serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT])) {
            $circularReferenceLimit = $serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT];
        }

        $context = new Context(
            $serializerContext[AbstractNormalizer::GROUPS] ?? null,
            $serializerContext[AbstractNormalizer::ATTRIBUTES] ?? null,
            $serializerContextContext[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? null
        );

        if (isset($serializerContext[AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS])) {
            foreach ($serializerContext[AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS] as $class => $keyArgs) {
                foreach ($keyArgs as $key => $value) {
                    $context->setConstructorArgument($class, $key, $value);
                }
            }
        }

        $context->setCircularReferenceLimit($circularReferenceLimit);
        $context->setObjectToPopulate($serializerContext[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null);
        $context->setCircularReferenceHandler($serializerContext[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER] ?? null);

        return $context;
    }
}
