<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization\Normalizer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * This normalizer is only used in Debug/Dev/Messenger contexts.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 */
final class FlattenExceptionNormalizer implements DenormalizerInterface, NormalizerInterface
{
    use NormalizerAwareTrait;

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'message' => $data->getMessage(),
            'code' => $data->getCode(),
            'headers' => $data->getHeaders(),
            'class' => $data->getClass(),
            'file' => $data->getFile(),
            'line' => $data->getLine(),
            'previous' => null === $data->getPrevious() ? null : $this->normalize($data->getPrevious(), $format, $context),
            'status' => $data->getStatusCode(),
            'status_text' => $data->getStatusText(),
            'trace' => $data->getTrace(),
            'trace_as_string' => $data->getTraceAsString(),
        ];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            FlattenException::class => false,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): FlattenException
    {
        $object = new FlattenException();

        $object->setMessage($data['message']);
        $object->setCode($data['code']);
        $object->setStatusCode($data['status'] ?? 500);
        $object->setClass($data['class']);
        $object->setFile($data['file']);
        $object->setLine($data['line']);
        $object->setStatusText($data['status_text']);
        $object->setHeaders((array) $data['headers']);

        if (isset($data['previous'])) {
            $object->setPrevious($this->denormalize($data['previous'], $type, $format, $context));
        }

        $property = new \ReflectionProperty(FlattenException::class, 'trace');
        $property->setValue($object, (array) $data['trace']);

        $property = new \ReflectionProperty(FlattenException::class, 'traceAsString');
        $property->setValue($object, $data['trace_as_string']);

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return FlattenException::class === $type && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }
}
