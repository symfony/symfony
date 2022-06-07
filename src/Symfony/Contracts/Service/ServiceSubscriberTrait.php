<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Service;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Attribute\SubscribedService;

/**
 * Implementation of ServiceSubscriberInterface that determines subscribed services from
 * method return types and property type-hints for methods/properties marked with the
 * "SubscribedService" attribute. Service ids are available as "ClassName::methodName"
 * for methods and "propertyName" for properties.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait ServiceSubscriberTrait
{
    /** @var ContainerInterface */
    protected $container;

    public static function getSubscribedServices(): array
    {
        $services = method_exists(get_parent_class(self::class) ?: '', __FUNCTION__) ? parent::getSubscribedServices() : [];
        $refClass = new \ReflectionClass(self::class);

        foreach ($refClass->getProperties() as $property) {
            if (self::class !== $property->getDeclaringClass()->name) {
                continue;
            }

            if (!$attribute = $property->getAttributes(SubscribedService::class)[0] ?? null) {
                continue;
            }

            if ($property->isStatic()) {
                throw new \LogicException(sprintf('Cannot use "%s" on property "%s::$%s" (can only be used on non-static properties with a type).', SubscribedService::class, self::class, $property->name));
            }

            if (!$type = $property->getType()) {
                throw new \LogicException(sprintf('Cannot use "%s" on properties without a type in "%s::%s()".', SubscribedService::class, $property->name, self::class));
            }

            /* @var SubscribedService $attribute */
            $attribute = $attribute->newInstance();
            $attribute->key ??= $property->name;
            $attribute->type ??= $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
            $attribute->nullable = $type->allowsNull();

            if ($attribute->attributes) {
                $services[] = $attribute;
            } else {
                $services[$attribute->key] = ($attribute->nullable ? '?' : '').$attribute->type;
            }
        }

        foreach ($refClass->getMethods() as $method) {
            if (self::class !== $method->getDeclaringClass()->name) {
                continue;
            }

            if (!$attribute = $method->getAttributes(SubscribedService::class)[0] ?? null) {
                continue;
            }

            if ($method->isStatic() || $method->isAbstract() || $method->isGenerator() || $method->isInternal() || $method->getNumberOfRequiredParameters()) {
                throw new \LogicException(sprintf('Cannot use "%s" on method "%s::%s()" (can only be used on non-static, non-abstract methods with no parameters).', SubscribedService::class, self::class, $method->name));
            }

            if (!$returnType = $method->getReturnType()) {
                throw new \LogicException(sprintf('Cannot use "%s" on methods without a return type in "%s::%s()".', SubscribedService::class, $method->name, self::class));
            }

            /* @var SubscribedService $attribute */
            $attribute = $attribute->newInstance();
            $attribute->key ??= self::class.'::'.$method->name;
            $attribute->type ??= $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType;
            $attribute->nullable = $returnType->allowsNull();

            if ($attribute->attributes) {
                $services[] = $attribute;
            } else {
                $services[$attribute->key] = ($attribute->nullable ? '?' : '').$attribute->type;
            }
        }

        return $services;
    }

    #[Required]
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $this->container = $container;

        foreach ((new \ReflectionClass(self::class))->getProperties() as $property) {
            if (self::class !== $property->getDeclaringClass()->name) {
                continue;
            }

            if (!$property->getAttributes(SubscribedService::class)) {
                continue;
            }

            unset($this->{$property->name});
        }

        if (method_exists(get_parent_class(self::class) ?: '', __FUNCTION__)) {
            return parent::setContainer($container);
        }

        return null;
    }

    public function __get(string $name): mixed
    {
        // TODO: ensure cannot be called from outside of the scope of the object?
        // TODO: what if class has a child/parent that allows this?
        // TODO: call parent::__get()?

        return $this->$name = $this->container->has($name) ? $this->container->get($name) : null;
    }
}
