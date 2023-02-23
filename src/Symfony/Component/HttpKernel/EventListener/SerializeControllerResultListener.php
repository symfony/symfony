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
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SerializeControllerResultListener implements EventSubscriberInterface
{
    public function __construct(private readonly ?SerializerInterface $serializer)
    {
    }

    public function onView(ViewEvent $event): void
    {
        /** @var Serialize[] $attributes */
        $attributes = $event->controllerArgumentsEvent->getAttributes()[Serialize::class] ?? [];
        if (!$attributes) {
            return;
        }

        if (!$this->serializer) {
            throw new \LogicException(sprintf('The "symfony/serializer" component is required to use the "%s" attribute. Try running "composer require symfony/serializer".', Serialize::class));
        }

        $request = $event->getRequest();
        $controllerResult = $event->getControllerResult();
        $format = $request->getRequestFormat('json');
        $attribute = $attributes[0];

        $data = $this->serializer->serialize($controllerResult, $format, $attribute->serializationContext);

        $headers = $attribute->headers + ['Content-Type' => $request->getMimeType($format)];
        $response = new Response($data, $attribute->code, $headers);

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => 'onView',
        ];
    }
}
