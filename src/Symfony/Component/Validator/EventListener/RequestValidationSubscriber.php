<?php

namespace Symfony\Component\Validator\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
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

        $attributeArguments = $attribute->getArguments();
        if(key_exists('class', $attributeArguments)) {
            $class = $attributeArguments['class'];
            $override = key_exists('override', $attributeArguments) ? $attributeArguments['override'] : true;
            $order = key_exists('order', $attributeArguments) ? $attributeArguments['order'] : [
                RequestValidator::ORDER_SERIALIZE,
                RequestValidator::ORDER_ATTRIBUTES,
                RequestValidator::ORDER_QUERY,
                RequestValidator::ORDER_REQUEST,
            ];
            $serializedFormat = key_exists('serializedFormat', $attributeArguments) ? $attributeArguments['json'] : 'json';
        }else {
            $class = $attributeArguments[0];
            $override = key_exists(1, $attributeArguments) ? $attributeArguments[1] : true;
            $order = key_exists(2, $attributeArguments) ? $attributeArguments[2] : [
                RequestValidator::ORDER_SERIALIZE,
                RequestValidator::ORDER_ATTRIBUTES,
                RequestValidator::ORDER_QUERY,
                RequestValidator::ORDER_REQUEST,
            ];
            $serializedFormat = key_exists(3, $attributeArguments) ? $attributeArguments[3] : 'json';
        }

        $object = new $class();

        foreach ($order as $type) {
            switch ($type) {
                case RequestValidator::ORDER_SERIALIZE:
                    if(empty($request->getContent())) {
                        continue 2;
                    }
                    $serializer = $this->getSerializer();
                    $serializer->deserialize($request->getContent(), $class, $serializedFormat,
                        [AbstractNormalizer::OBJECT_TO_POPULATE => $object]);
                    continue 2;
                case RequestValidator::ORDER_REQUEST:
                    $this->setProperties($object, $request->request->all(), $override);
                    break;
                case RequestValidator::ORDER_QUERY:
                    $this->setProperties($object, $request->query->all(), $override);
                    break;
                case RequestValidator::ORDER_ATTRIBUTES:
                    $this->setProperties($object, $request->attributes->all(), $override);
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

    private function setProperties(object $object, array $parameters, bool $override) {
        foreach ($parameters as $key => $value) {
            if(false === $override && property_exists($object, $key) && isset($object->{$key})) {
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
        if (!class_exists(Serializer::class)) {
            throw new LogicException(sprintf('The "symfony/serializer" component is required to use the "%s" validator. Try running "composer require symfony/serializer".',
                __CLASS__));
        }

        return $this->serializer;
    }
}
