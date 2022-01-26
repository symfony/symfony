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
use Symfony\Component\HttpKernel\Attribute\ParamConverter;
use Symfony\Component\HttpKernel\Controller\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ParamConverterListener handles the ParamConverter annotation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParamConverterListener implements EventSubscriberInterface
{
    /**
     * @var ParamConverterManager
     */
    private $manager;

    private $autoConvert;

    /**
     * @param bool $autoConvert Auto convert non-configured objects
     */
    public function __construct(ParamConverterManager $manager, $autoConvert = true)
    {
        $this->manager = $manager;
        $this->autoConvert = $autoConvert;
    }

    /**
     * Modifies the ParamConverterManager instance.
     */
    public function onKernelController(KernelEvent $event)
    {
        $request = $event->getRequest();
        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return;
        }

        $className = \get_class($controller[0]);
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $classConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $object->getAttributes(ParamConverter::class)
        );
        $methodConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $method->getAttributes(ParamConverter::class)
        );
        $rawConfigurations = array_merge($classConfigurations, $methodConfigurations);

        $configurations = [];

        foreach ($rawConfigurations as $configuration) {
            $configurations[$configuration->getName()] = $configuration;
        }

        // automatically apply conversion for non-configured objects
        if ($this->autoConvert) {
            if (\is_array($controller)) {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
            } elseif (\is_object($controller) && \is_callable([$controller, '__invoke'])) {
                $r = new \ReflectionMethod($controller, '__invoke');
            } else {
                $r = new \ReflectionFunction($controller);
            }

            $configurations = $this->autoConfigure($r, $request, $configurations);
        }

        $this->manager->apply($request, $configurations);
    }

    private function autoConfigure(\ReflectionFunctionAbstract $r, Request $request, $configurations)
    {
        foreach ($r->getParameters() as $param) {
            $type = $param->getType();
            $class = $this->getParamClassByType($type);
            if (null !== $class && $request instanceof $class) {
                continue;
            }

            $name = $param->getName();

            if ($type) {
                if (!isset($configurations[$name])) {
                    $configuration = new ParamConverter($name);

                    $configurations[$name] = $configuration;
                }

                if (null !== $class && null === $configurations[$name]->getClass()) {
                    $configurations[$name]->setClass($class);
                }
            }

            if (isset($configurations[$name])) {
                $configurations[$name]->setIsOptional($param->isOptional() || $param->isDefaultValueAvailable() || ($type && $type->allowsNull()));
            }
        }

        return $configurations;
    }

    private function getParamClassByType(?\ReflectionType $type): ?string
    {
        if (null === $type) {
            return null;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $type) {
            if (!$type->isBuiltin()) {
                return $type->getName();
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
