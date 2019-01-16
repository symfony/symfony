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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Compiler pass to register tagged services that require a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterServiceSubscribersPass extends AbstractRecursivePass
{
    protected function processValue($value, $isRoot = false)
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
            if (!array_key_exists('id', $attributes)) {
                throw new InvalidArgumentException(sprintf('Missing "id" attribute on "container.service_subscriber" tag with key="%s" for service "%s".', $attributes['key'], $this->currentId));
            }
            if (!array_key_exists('key', $attributes)) {
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
        $declaringClass = (new \ReflectionMethod($class, 'getSubscribedServices'))->class;

        foreach ($class::getSubscribedServices() as $key => $type) {
            if (!\is_string($type) || !preg_match('/^\??[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $type)) {
                throw new InvalidArgumentException(sprintf('"%s::getSubscribedServices()" must return valid PHP types for service "%s" key "%s", "%s" returned.', $class, $this->currentId, $key, \is_string($type) ? $type : \gettype($type)));
            }
            if ($optionalBehavior = '?' === $type[0]) {
                $type = substr($type, 1);
                $optionalBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            }
            if (\is_int($key)) {
                $key = $type;
            }
            if (!isset($serviceMap[$key])) {
                if (!$autowire) {
                    throw new InvalidArgumentException(sprintf('Service "%s" misses a "container.service_subscriber" tag with "key"/"id" attributes corresponding to entry "%s" as returned by "%s::getSubscribedServices()".', $this->currentId, $key, $class));
                }
                $serviceMap[$key] = new Reference($type);
            }

            $subscriberMap[$key] = new TypedReference($this->container->normalizeId($serviceMap[$key]), $type, $declaringClass, $optionalBehavior ?: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
            unset($serviceMap[$key]);
        }

        if ($serviceMap = array_keys($serviceMap)) {
            $message = sprintf(1 < \count($serviceMap) ? 'keys "%s" do' : 'key "%s" does', str_replace('%', '%%', implode('", "', $serviceMap)));
            throw new InvalidArgumentException(sprintf('Service %s not exist in the map returned by "%s::getSubscribedServices()" for service "%s".', $message, $class, $this->currentId));
        }

        $value->addTag('container.service_subscriber.locator', ['id' => (string) ServiceLocatorTagPass::register($this->container, $subscriberMap, $this->currentId)]);

        return parent::processValue($value);
    }
}
