<?php

namespace Symfony\Bundle\FrameworkBundle\ArgumentResolver;

use Symfony\Bundle\FrameworkBundle\Exception\UnparsableInputException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Unserialize & validate user input.
 * Works in duo with Symfony\Bundle\FrameworkBundle\EventListener\InputValidationFailedExceptionListener.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class UserInputResolver implements ArgumentValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator, private SerializerInterface $serializer)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        $className = $argument->getType();

        return class_exists($className) && \in_array(UserInputInterface::class, class_implements($className) ?: [], true);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        try {
            $input = $this->serializer->deserialize($request->getContent(), $argument->getType(), $request->attributes->get('_format', 'json'));
        } catch (ExceptionInterface $exception) {
            throw new UnparsableInputException($exception->getMessage(), 0, $exception);
        }

        $errors = $this->validator->validate($input);

        if ($errors->count() > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        yield $input;
    }
}
