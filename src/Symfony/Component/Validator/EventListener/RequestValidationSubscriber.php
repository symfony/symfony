<?php

namespace Symfony\Component\Validator\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Attribute\RequestValidator;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(readonly ?SerializerInterface $serializer = null, readonly ValidatorInterface $validator)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            ControllerArgumentsEvent::class => 'validateRequest'
        ];
    }

    public function validateRequest(ControllerArgumentsEvent $event): void {
        $controller = $event->getController();
        $arguments = $event->getArguments();
        $reflectionMethod = $this->getReflectionMethod($controller);
        $request = $event->getRequest();

        $attributes = $reflectionMethod->getAttributes(RequestValidator::class, \ReflectionAttribute::IS_INSTANCEOF);

        if(count($attributes) === 0) {
            return;
        }

        // only first attribute can validate
        $attribute = $attributes[0];

        $class = $attribute->getArguments()['class'];

        $object = new $class();

        // if serializer is installed serialize input body
        if(null !== $this->serializer) {
            $object = $this->serializer->deserialize($request->getContent(), $class, 'json');
        }

        // set input variables in object
        foreach ($request->request as $key => $input) {
            $object->{$key} = $input;
        }

        // set parameter variables in object
        foreach ($request->attributes as $key => $parameter) {
            $object->{$key} = $parameter;
        }

        $violations = $this->validator->validate($object);

        if(count($violations) > 0) {
            throw new ValidationFailedException(sprintf("Validation of %s failed!", $class), $violations);
        }

        foreach ($arguments as $index => $argument) {
            if(!$argument instanceof $class) {
                continue;
            }
            $arguments[$index] = $object;
        }

        $event->setArguments($arguments);
    }

    private function getReflectionMethod(callable $controller): \ReflectionMethod
    {
        if (is_array($controller)) {
            $class = $controller[0];
            $method = $controller[1];
        } else {
            /** @var object $controller */
            $class = $controller;
            $method = '__invoke';
        }

        return new \ReflectionMethod($class, $method);
    }
}
