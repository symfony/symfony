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
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SerializeControllerResultAttributeListener implements EventSubscriberInterface
{
    public function __construct(private readonly ?SerializerInterface $serializer)
    {
    }

    /**
     * @param ControllerAttributeEvent<Serialize> $event
     */
    public function onView(ControllerAttributeEvent $event): void
    {
        $kernelEvent = $event->kernelEvent;

        if (!$kernelEvent instanceof ViewEvent) {
            return;
        }

        if (!$this->serializer) {
            throw new \LogicException(\sprintf('The "symfony/serializer" component is required to use the "#[%s]" attribute. Try running "composer require symfony/serializer".', Serialize::class));
        }

        $request = $kernelEvent->getRequest();
        $controllerResult = $kernelEvent->getControllerResult();
        $format = $request->getRequestFormat('json');

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
}
