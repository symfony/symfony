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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Annotation\Input;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\InputValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Deserialize & validate user input.
 *
 * Works in duo with Symfony\Bundle\FrameworkBundle\EventListener\InputValidationFailedExceptionListener.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class UserInputResolver implements ArgumentValueResolverInterface
{
    public function __construct(private SerializerInterface $serializer, private ?ValidatorInterface $validator = null)
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
        $context = array_merge($attribute->serializationContext, [
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
        ]);
        $format = $attribute->format ?? $request->attributes->get('_format', 'json');

        try {
            $input = $this->serializer->deserialize(data: $request->getContent(), type: $argument->getType(), format: $format, context: $context);
        } catch (PartialDenormalizationException $e) {
            if (null === $this->validator) {
                throw new UnprocessableEntityHttpException(message: $e->getMessage(), previous: $e);
            }

            $errors = new ConstraintViolationList();

            foreach ($e->getErrors() as $exception) {
                $message = sprintf('The type must be one of "%s" ("%s" given).', implode(', ', $exception->getExpectedTypes()), $exception->getCurrentType());
                $parameters = [];

                if ($exception->canUseMessageForUser()) {
                    $parameters['hint'] = $exception->getMessage();
                }

                $errors->add(new ConstraintViolation($message, '', $parameters, null, $exception->getPath(), null));
            }

            throw new InputValidationFailedException(null, $errors);
        }

        if ($this->validator) {
            $errors = $this->validator->validate(value: $input, groups: $attribute->validationGroups);

            if ($errors->count() > 0) {
                throw new InputValidationFailedException($input, $errors);
            }
        }

        yield $input;
    }

    private function getAttribute(ArgumentMetadata $argument): ?Input
    {
        return $argument->getAttributes(Input::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
    }
}
