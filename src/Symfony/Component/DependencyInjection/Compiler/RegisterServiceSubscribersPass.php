<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Compiler pass to register tagged services that require a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterServiceSubscribersPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof Definition || $value->isAbstract() || $value->isSynthetic() || !$value->hasTag('container.service_subscriber')) {
            return parent::processValue($value, $isRoot);
        }

        $serviceMap = [];
        $autowire = $value->isAutowired();

        foreach ($value->getTag('container.service_subscriber') as $attributes) {
            if (!$attributes) {
                $autowire = true;
                continue;
            }
            ksort($attributes);
            if ([] !== array_diff(array_keys($attributes), ['id', 'key'])) {
                throw new InvalidArgumentException(sprintf('The "container.service_subscriber" tag accepts only the "key" and "id" attributes, "%s" given for service "%s".', implode('", "', array_keys($attributes)), $this->currentId));
            }
            if (!\array_key_exists('id', $attributes)) {
                throw new InvalidArgumentException(sprintf('Missing "id" attribute on "container.service_subscriber" tag with key="%s" for service "%s".', $attributes['key'], $this->currentId));
            }
            if (!\array_key_exists('key', $attributes)) {
                $attributes['key'] = $attributes['id'];
            }
            if (isset($serviceMap[$attributes['key']])) {
                continue;
            }
            $serviceMap[$attributes['key']] = new Reference($attributes['id']);
        }
        $class = $value->getClass();

        if (!$r = $this->container->getReflectionClass($class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $this->currentId));
        }
        if (!$r->isSubclassOf(ServiceSubscriberInterface::class)) {
            throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $this->currentId, ServiceSubscriberInterface::class));
        }
        $class = $r->name;
        $subscriberMap = [];

        foreach ($class::getSubscribedServices() as $key => $type) {
            $attributes = [];

            if (!isset($serviceMap[$key]) && $type instanceof Autowire) {
                $subscriberMap[$key] = $type;
                continue;
            }

            if ($type instanceof SubscribedService) {
                $key = $type->key ?? $key;
                $attributes = $type->attributes;
                $type = ($type->nullable ? '?' : '').($type->type ?? throw new InvalidArgumentException(sprintf('When "%s::getSubscribedServices()" returns "%s", a type must be set.', $class, SubscribedService::class)));
            }

            if (!\is_string($type) || !preg_match('/(?(DEFINE)(?<cn>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))(?(DEFINE)(?<fqcn>(?&cn)(?:\\\\(?&cn))*+))^\??(?&fqcn)(?:(?:\|(?&fqcn))*+|(?:&(?&fqcn))*+)$/', $type)) {
                throw new InvalidArgumentException(sprintf('"%s::getSubscribedServices()" must return valid PHP types for service "%s" key "%s", "%s" returned.', $class, $this->currentId, $key, \is_string($type) ? $type : get_debug_type($type)));
            }
            $optionalBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if ('?' === $type[0]) {
                $type = substr($type, 1);
                $optionalBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            }
            if (\is_int($name = $key)) {
                $key = $type;
                $name = null;
            }
            if (!isset($serviceMap[$key])) {
                if (!$autowire) {
                    throw new InvalidArgumentException(sprintf('Service "%s" misses a "container.service_subscriber" tag with "key"/"id" attributes corresponding to entry "%s" as returned by "%s::getSubscribedServices()".', $this->currentId, $key, $class));
                }
                $serviceMap[$key] = new Reference($type);
            }

            if ($name) {
                if (false !== $i = strpos($name, '::get')) {
                    $name = lcfirst(substr($name, 5 + $i));
                } elseif (str_contains($name, '::')) {
                    $name = null;
                }
            }

            if (null !== $name && !$this->container->has($name) && !$this->container->has($type.' $'.$name)) {
                $camelCaseName = lcfirst(str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $name))));
                $name = $this->container->has($type.' $'.$camelCaseName) ? $camelCaseName : $name;
            }

            $subscriberMap[$key] = new TypedReference((string) $serviceMap[$key], $type, $optionalBehavior, $name, $attributes);
            unset($serviceMap[$key]);
        }

        if ($serviceMap = array_keys($serviceMap)) {
            $message = sprintf(1 < \count($serviceMap) ? 'keys "%s" do' : 'key "%s" does', str_replace('%', '%%', implode('", "', $serviceMap)));
            throw new InvalidArgumentException(sprintf('Service %s not exist in the map returned by "%s::getSubscribedServices()" for service "%s".', $message, $class, $this->currentId));
        }

        $locatorRef = ServiceLocatorTagPass::register($this->container, $subscriberMap, $this->currentId);

        $value->addTag('container.service_subscriber.locator', ['id' => (string) $locatorRef]);

        $value->setBindings([
            PsrContainerInterface::class => new BoundArgument($locatorRef, false),
            ServiceProviderInterface::class => new BoundArgument($locatorRef, false),
        ] + $value->getBindings());

        return parent::processValue($value);
    }
}
