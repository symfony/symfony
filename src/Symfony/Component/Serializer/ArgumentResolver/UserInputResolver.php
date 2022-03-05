<?php

namespace Symfony\Component\Serializer\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Annotation\Input;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
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
    public function __construct(private ValidatorInterface $validator, private SerializerInterface $serializer, )
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
        $context = array_merge($attribute->getSerializationContext(), [
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
        ]);
        $format = $attribute->getFormat() ?? $request->attributes->get('_format', 'json');

        $input = null;
        try {
            $input = $this->serializer->deserialize(data: $request->getContent(), type: $argument->getType(), format: $format, context: $context);

            $errors = $this->validator->validate(value: $input, groups: $attribute->getValidationGroups());
        } catch (PartialDenormalizationException $e) {
            $errors = new ConstraintViolationList();

            foreach ($e->getErrors() as $exception) {
                $message = sprintf('The type must be one of "%s" ("%s" given).', implode(', ', $exception->getExpectedTypes()), $exception->getCurrentType());
                $parameters = [];

                if ($exception->canUseMessageForUser()) {
                    $parameters['hint'] = $exception->getMessage();
                }

                $errors->add(new ConstraintViolation($message, '', $parameters, null, $exception->getPath(), null));
            }
        }

        if ($errors->count() > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        yield $input;
    }

    private function getAttribute(ArgumentMetadata $argument): ?Input
    {
        return $argument->getAttributes(Input::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
    }
}
