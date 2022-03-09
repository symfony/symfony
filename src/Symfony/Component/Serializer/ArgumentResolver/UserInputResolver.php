<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Annotation\RequestBody;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Deserialize request body if Symfony\Component\Serializer\Annotation\RequestBody attribute is present on an argument.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class UserInputResolver implements ArgumentValueResolverInterface
{
    public function __construct(private SerializerInterface $serializer)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return null !== $this->getAttribute($argument);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $this->getAttribute($argument);
        $context = array_merge($attribute->context, [
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
        ]);
        $format = $attribute->format ?? $request->getContentType() ?? 'json';

        yield $this->serializer->deserialize($request->getContent(), $argument->getType(), $format, $context);
    }

    private function getAttribute(ArgumentMetadata $argument): ?RequestBody
    {
        return $argument->getAttributes(RequestBody::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
    }
}
