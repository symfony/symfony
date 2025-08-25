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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\SerializeResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Automatically serializes controller return values when decorated with #[SerializeResponse].
 */
class SerializeResponseListener implements EventSubscriberInterface
{
    public function __construct(
        private ?SerializerInterface $serializer,
    ) {
    }

    public function onKernelView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$attribute = $event->controllerArgumentsEvent?->getAttributes(SerializeResponse::class)[0] ?? null) {
            return;
        }

        if (null === $this->serializer) {
            return;
        }

        $serializedData = $this->serializer->serialize($result, $attribute->format, $attribute->serializationContext);

        $headers = $attribute->headers;

        if (!isset($headers['Content-Type']) && !isset($headers['content-type']) && ($contentType = $this->getContentTypeForFormat($attribute->format))) {
            $headers['Content-Type'] = $contentType;
        }

        $response = new Response(
            $serializedData,
            $attribute->status,
            $headers,
        );

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onKernelView', -128],
        ];
    }

    private function getContentTypeForFormat(string $format): ?string
    {
        return match ($format) {
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yaml', 'yml' => 'application/x-yaml',
            'csv' => 'text/csv',
            default => null,
        };
    }
}
