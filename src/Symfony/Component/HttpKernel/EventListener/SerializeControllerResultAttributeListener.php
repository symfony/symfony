<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SerializeControllerResultAttributeListener implements EventSubscriberInterface
{
    /**
     * @param SerializerInterface|(\Closure(): ?SerializerInterface)|null     $serializer
     * @param ObjectMapperInterface|(\Closure(): ?ObjectMapperInterface)|null $objectMapper
     * @param StreamWriterInterface|(\Closure(): ?StreamWriterInterface)|null $streamWriter
     * @param TypeResolverInterface|(\Closure(): ?TypeResolverInterface)|null $typeResolver
     */
    public function __construct(
        private SerializerInterface|\Closure|null $serializer,
        private ObjectMapperInterface|\Closure|null $objectMapper = null,
        private StreamWriterInterface|\Closure|null $streamWriter = null,
        private TypeResolverInterface|\Closure|null $typeResolver = null,
    ) {
    }

    /**
     * @param ControllerAttributeEvent<Serialize, ViewEvent> $event
     */
    public function onView(ControllerAttributeEvent $event): void
    {
        $kernelEvent = $event->kernelEvent;

        if (!$kernelEvent instanceof ViewEvent) {
            return;
        }

        if ($event->attribute->stream) {
            if (!self::resolve($this->streamWriter)) {
                throw new \LogicException(\sprintf('The "symfony/json-streamer" component is required to use the "stream" option of the "#[%s]" attribute. Try running "composer require symfony/json-streamer".', Serialize::class));
            }
        } elseif (!self::resolve($this->serializer)) {
            throw new \LogicException(\sprintf('The "symfony/serializer" component is required to use the "#[%s]" attribute. Try running "composer require symfony/serializer".', Serialize::class));
        }

        $request = $kernelEvent->getRequest();
        $controllerResult = $kernelEvent->getControllerResult();
        $format = $request->getRequestFormat('json');

        if ($event->attribute->stream && 'json' !== $format) {
            throw new UnsupportedMediaTypeHttpException(\sprintf('Unsupported format "%s".', $format));
        }

        if (null !== $event->attribute->map) {
            $controllerResult = $this->map($controllerResult, $event->attribute->map);
        }

        if ($event->attribute->stream) {
            $type = $this->resolveType($controllerResult, $event->attribute->map, $kernelEvent);
            $data = $this->streamWriter->write($controllerResult, $type, $event->attribute->context);
            $headers = $this->mergeHeaders($event->attribute, $request, $format);
            $kernelEvent->setResponse(new StreamedResponse($data, $event->attribute->code, $headers));

            return;
        }

        try {
            $data = $this->serializer->serialize($controllerResult, $format, $event->attribute->context);
        } catch (UnsupportedFormatException $exception) {
            throw new UnsupportedMediaTypeHttpException(\sprintf('Unsupported format "%s".', $format), $exception->getPrevious());
        }

        $headers = $this->mergeHeaders($event->attribute, $request, $format);
        $response = new Response($data, $event->attribute->code, $headers);

        $kernelEvent->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW.'.'.Serialize::class => 'onView',
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function mergeHeaders(Serialize $attribute, Request $request, string $format): array
    {
        $headers = array_combine(
            array_map('strtolower', array_keys($attribute->headers)),
            array_values($attribute->headers),
        );

        if (!isset($headers['content-type'])) {
            $headers['content-type'] = $request->getMimeType($format);
        }

        return $headers;
    }

    private function map(mixed $value, string $class): mixed
    {
        if (!self::resolve($this->objectMapper)) {
            throw new \LogicException(\sprintf('The "symfony/object-mapper" component is required to use the "map" option of the "#[%s]" attribute. Try running "composer require symfony/object-mapper".', Serialize::class));
        }

        if (\is_array($value)) {
            return array_map(fn ($item) => \is_object($item) ? $this->objectMapper->map($item, $class) : $item, $value);
        }

        if ($value instanceof \Traversable) {
            return (function () use ($value, $class) {
                foreach ($value as $key => $item) {
                    yield $key => \is_object($item) ? $this->objectMapper->map($item, $class) : $item;
                }
            })();
        }

        return \is_object($value) ? $this->objectMapper->map($value, $class) : $value;
    }

    private function resolveType(mixed $controllerResult, ?string $map, ViewEvent $event): Type
    {
        if (null !== $map) {
            return match (true) {
                is_iterable($controllerResult) => Type::list(Type::object($map)),
                null === $controllerResult => Type::null(),
                default => Type::object($map),
            };
        }

        try {
            return (self::resolve($this->typeResolver) ?? $this->typeResolver = TypeResolver::create())->resolve($event->controllerMetadata?->getReflector());
        } catch (UnsupportedException $e) {
            throw new \LogicException(\sprintf('The "stream" option of the "#[%s]" attribute requires the "map" option or a return type on the controller: %s', Serialize::class, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @template T of object
     *
     * @param T|(\Closure(): ?T)|null $dependency
     *
     * @param-out T|null $dependency
     *
     * @return T|null
     */
    private static function resolve(?object &$dependency): ?object
    {
        return $dependency instanceof \Closure ? $dependency = $dependency() : $dependency;
    }
}
