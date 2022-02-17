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

use Doctrine\Persistence\Proxy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Attribute\ControllerAttributeInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ControllerAttributeListener class parses attributes marked
 * as controller attributes in controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
class ControllerAttributeListener implements EventSubscriberInterface
{
    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return;
        }

        $className = self::getRealClass(\get_class($controller[0]));
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $classAttributes = $object->getAttributes(ControllerAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);
        $methodAttributes = $method->getAttributes(ControllerAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

        $attributes = [];
        foreach (array_merge($classAttributes, $methodAttributes) as $attribute) {
            if ($attribute->isRepeated()) {
                $attributes[$attribute->getName()][] = $attribute->newInstance();
            } else {
                // method attribute overrides class attribute
                $attributes[$attribute->getName()] = $attribute->newInstance();
            }
        }

        $request = $event->getRequest();
        $request->attributes->set('_controller_attributes', $attributes);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    private static function getRealClass(string $class): string
    {
        if (class_exists(Proxy::class)) {
            if (false === $pos = strrpos($class, '\\'.Proxy::MARKER.'\\')) {
                return $class;
            }

            return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
        }

        return $class;
    }
}
