<?php

namespace Symfony\Component\Validator\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Attribute\RequestValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        readonly ValidatorInterface $validator,
        readonly ?SerializerInterface $serializer = null
    ) {
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

        if (count($attributes) === 0) {
            return;
        }

        // only first attribute can validate
        $attribute = $attributes[0];

        $class = $attribute->getArguments()['class'];
        $override = $attribute->getArguments()['override'];
        $serializedFormat = $attribute->getArguments()['serializedFormat'];
        $order = $attribute->getArguments()['order'];

        $object = new $class();

        foreach ($order as $type) {
            switch ($type) {
                case RequestValidator::ORDER_SERIALIZE:
                    $serializer = $this->getSerializer();
                    $serializer->deserialize($request->getContent(), $class, $serializedFormat,
                        [AbstractNormalizer::OBJECT_TO_POPULATE => $object]);
                    continue 2;
                case RequestValidator::ORDER_REQUEST:
                    $this->setProperties($object, $request->request, $override);
                    break;
                case RequestValidator::ORDER_QUERY:
                    $this->setProperties($object, $request->query, $override);
                    break;
                case RequestValidator::ORDER_ATTRIBUTES:
                    $this->setProperties($object, $request->attributes, $override);
                    break;
            }

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

    private function setProperties(object $object, \IteratorAggregate $bag, bool $override) {
        foreach ($bag as $key => $value) {
            if(false === $override && property_exists($object, $key)) {
                continue;
            }
            $object->{$key} = $value;
        }
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

    private function getSerializer(): SerializerInterface
    {
        if (!class_exists(SerializerInterface::class)) {
            throw new LogicException(sprintf('The "symfony/serializer" component is required to use the "%s" validator. Try running "composer require symfony/serializer".',
                __CLASS__));
        }

        return $this->serializer;
    }
}
