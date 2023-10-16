<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestHeader;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestHeaderValueResolver implements ValueResolverInterface
{
    /**
     * @see DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS
     */
    private const CONTEXT_DESERIALIZE = [
        'collect_denormalization_errors' => true,
    ];

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ?ValidatorInterface $validator = null,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$attribute = $argument->getAttributesOfType(MapRequestHeader::class)[0] ?? null) {
            return [];
        }

        $headers = [];
        $requestHeaders = $request->headers->all();

        array_walk($requestHeaders, function ($value, $key) use (&$headers) {
            $headers[$key] = implode(',', $value);
        });

        $type = $argument->getType();

        if ('string' === $type || 'array' === $type) {
            $name = $attribute->name ?? $argument->getName();

            if (!isset($headers[$name])) {
                return [null];
            }

            if ('string' === $type) {
                return [$headers[$name]];
            }

            return [explode(',', $headers[$name])];
        }

        try {
            $payload = $this->serializer->denormalize($headers, $argument->getType(), null, self::CONTEXT_DESERIALIZE + $attribute->serializationContext);
        } catch (PartialDenormalizationException $e) {
            throw new HttpException($attribute->validationFailedStatusCode, implode("\n", array_map(static fn ($e) => $e->getMessage(), $e->getErrors())), $e);
        }

        if ($this->validator) {
            $violations = new ConstraintViolationList();
            $violations->addAll($this->validator->validate($payload, null, $attribute->validationGroups));

            if (\count($violations)) {
                throw new HttpException($attribute->validationFailedStatusCode, implode("\n", array_map(static fn ($e) => $e->getMessage(), iterator_to_array($violations))), new ValidationFailedException($payload, $violations));
            }
        }

        if (null === $payload) {
            $payload = match (true) {
                $argument->hasDefaultValue() => $argument->getDefaultValue(),
                $argument->isNullable() => null,
                default => throw new HttpException($attribute->validationFailedStatusCode)
            };
        }

        return [$payload];
    }
}
